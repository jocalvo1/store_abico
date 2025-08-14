<?php
// Sales processing controller
// Path: controller/sale/SalesController.php

require_once __DIR__ . '/../../includes/database.php';

class SalesController {
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
        $this->db->set_charset('utf8mb4');
    }

    public function process(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // Basic session check (expect user_id set by auth)
        if (session_status() === PHP_SESSION_NONE) session_start();
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

        $cartJson = $_POST['cart_json'] ?? '';
        // Accept payment_method_id directly; fallback to payment_type (name)
        $paymentMethodId = isset($_POST['payment_method_id']) && $_POST['payment_method_id'] !== '' ? (int)$_POST['payment_method_id'] : null;
        $paymentType = trim($_POST['payment_type'] ?? ''); // name fallback from UI
        $amountPaid = (float)($_POST['amount_paid'] ?? 0);
        $tenderedAmount = $amountPaid; // preserve original tendered before any adjustments
        $payLater = isset($_POST['pay_later']) && (string)$_POST['pay_later'] === '1' ? 1 : 0;
        $customerId = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? (int)$_POST['customer_id'] : null;
        $notes = trim($_POST['notes'] ?? '');

        $errors = [];
        if (!$cartJson) $errors[] = 'Missing cart data';
        if ($paymentMethodId === null && $paymentType === '') $errors[] = 'Missing payment method';
        $cart = json_decode($cartJson, true);
        if (!is_array($cart) || empty($cart)) $errors[] = 'Cart is empty';
        if (!empty($errors)) { $this->fail($errors); return; }

        // Resolve payment method id by name if needed
        if ($paymentMethodId === null && $paymentType !== '') {
            $stmtPm = $this->db->prepare('SELECT id FROM payment_methods WHERE name = ?');
            if ($stmtPm) {
                $stmtPm->bind_param('s', $paymentType);
                $stmtPm->execute();
                $resPm = $stmtPm->get_result();
                if ($rowPm = $resPm->fetch_assoc()) $paymentMethodId = (int)$rowPm['id'];
                $resPm->free();
                $stmtPm->close();
            }
        }
        if ($paymentMethodId === null) { $this->fail(['Unknown payment method.']); return; }

        // Recalculate totals and validate against stock
        $total = 0.0;
        $sanitizedItems = [];

        // Prepare statements
        $stmtGetItem = $this->db->prepare('SELECT id, name, unit, selling_price, current_stock FROM items WHERE id = ? FOR UPDATE');
        if (!$stmtGetItem) { $this->fail(['Failed to prepare item query.']); return; }

        $this->db->begin_transaction();
        try {
            foreach ($cart as $row) {
                $itemId = (int)($row['id'] ?? 0);
                $qty = (int)($row['quantity'] ?? 0);
                if ($itemId <= 0 || $qty <= 0) throw new Exception('Invalid cart item.');

                $stmtGetItem->bind_param('i', $itemId);
                $stmtGetItem->execute();
                $res = $stmtGetItem->get_result();
                $dbItem = $res->fetch_assoc();
                $res->free();
                if (!$dbItem) throw new Exception('Item not found: ' . $itemId);

                $price = (float)$dbItem['selling_price'];
                $stock = (int)$dbItem['current_stock'];
                if ($qty > $stock) throw new Exception('Insufficient stock for item ID ' . $itemId);

                $lineTotal = $price * $qty;
                $total += $lineTotal;
                $sanitizedItems[] = [
                    'id' => (int)$dbItem['id'],
                    'name' => $dbItem['name'],
                    'unit' => $dbItem['unit'],
                    'price' => $price,
                    'quantity' => $qty,
                    'line_total' => $lineTotal,
                ];
            }

            // Determine status based on payment
            // paid: amountPaid >= total and not pay later
            // partial: amountPaid > 0 and < total
            // debt: pay later or amountPaid == 0 and total > 0
            $status = 'paid';
            if ($payLater || $amountPaid == 0.0) {
                $status = ($amountPaid > 0.0 ? 'partial' : 'debt');
            }
            if (!$payLater && $amountPaid > 0.0 && $amountPaid < $total) $status = 'partial';

            // Insert into sales_transactions per schema
            $sqlTx = 'INSERT INTO sales_transactions (customer_id, transaction_date, payment_method_id, total_amount, status, created_by_user_id, notes, created_at) VALUES (' .
                ($customerId === null ? 'NULL' : '?') . ', NOW(), ?, ?, ?, ?, ?, NOW())';
            $stmtTx = $this->db->prepare($sqlTx);
            if (!$stmtTx) throw new Exception('Failed to prepare transaction insert.');

            $balance = 0.0;
            $change = 0.0;
            if ($payLater) {
                $balance = $total; // entire amount deferred
                $amountPaid = 0.0;
            } else {
                if ($amountPaid < $total) {
                    $balance = $total - $amountPaid;
                    $change = 0.0;
                } else {
                    $balance = 0.0;
                    $change = $amountPaid - $total;
                }
            }

            if ($customerId === null) {
                $stmtTx->bind_param(
                    'idsis',
                    $paymentMethodId,
                    $total,
                    $status,
                    $userId,
                    $notes
                );
            } else {
                $stmtTx->bind_param(
                    'iidsis',
                    $customerId,
                    $paymentMethodId,
                    $total,
                    $status,
                    $userId,
                    $notes
                );
            }
            if (!$stmtTx->execute()) throw new Exception('Failed to insert sales transaction.');

            $transactionId = $stmtTx->insert_id;
            $stmtTx->close();

            // Record payment for fully-paid sales (no debt). Use applied amount (exclude change)
            if (!$payLater && $tenderedAmount > 0.0 && $tenderedAmount >= $total) {
                $appliedAmount = min($tenderedAmount, $total);
                $changeAmount = max($tenderedAmount - $total, 0.0);
                $stmtPayFull = $this->db->prepare('INSERT INTO payment_records (sales_transaction_id, payment_date, payment_method_id, amount, amount_tendered, total_due, change_amount, received_by_user_id, notes, created_at) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, NOW())');
                if (!$stmtPayFull) throw new Exception('Failed to prepare full payment record insert.');
                $stmtPayFull->bind_param('iiddddis', $transactionId, $paymentMethodId, $appliedAmount, $tenderedAmount, $total, $changeAmount, $userId, $notes);
                if (!$stmtPayFull->execute()) throw new Exception('Failed to insert full payment record.');
                $stmtPayFull->close();
            }

            // Insert items and update stock + stock movement
            $stmtItem = $this->db->prepare('INSERT INTO sales_transaction_items (sales_transaction_id, item_id, quantity, unit_price, notes) VALUES (?, ?, ?, ?, NULL)');
            if (!$stmtItem) throw new Exception('Failed to prepare items insert.');

            $stmtUpdateStock = $this->db->prepare('UPDATE items SET current_stock = current_stock - ? WHERE id = ?');
            if (!$stmtUpdateStock) throw new Exception('Failed to prepare stock update.');

            $stmtMovement = $this->db->prepare('INSERT INTO stock_movements (item_id, movement_type, quantity, reference_type, reference_id, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            if (!$stmtMovement) throw new Exception('Failed to prepare stock movement insert.');

            foreach ($sanitizedItems as $it) {
                $itItemId = $it['id'];
                $itQty = $it['quantity'];
                $itPrice = $it['price'];

                $stmtItem->bind_param('iiid', $transactionId, $itItemId, $itQty, $itPrice);
                if (!$stmtItem->execute()) throw new Exception('Failed to insert transaction item.');

                $stmtUpdateStock->bind_param('ii', $itQty, $itItemId);
                if (!$stmtUpdateStock->execute()) throw new Exception('Failed to update stock.');

                $movementType = 'out';
                $refType = 'sales';
                $noteStr = 'Sale TX #' . $transactionId;
                $stmtMovement->bind_param('isisis', $itItemId, $movementType, $itQty, $refType, $transactionId, $noteStr);
                if (!$stmtMovement->execute()) throw new Exception('Failed to insert stock movement.');
            }

            // Create sales_debt record if applicable
            $insertedDebtId = null;
            if ($payLater || ($amountPaid > 0.0 && $amountPaid < $total) || ($amountPaid == 0.0 && $total > 0.0)) {
                if (!$customerId) throw new Exception('A customer is required for pay-later or outstanding balance.');
                $stmtDebt = $this->db->prepare('INSERT INTO sales_debts (sales_transaction_id, customer_id, total_amount, amount_paid, due_date, status, notes, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, ?, ?, NOW(), NOW())');
                if (!$stmtDebt) throw new Exception('Failed to prepare debt insert.');
                $debtStatus = 'unpaid';
                if ($amountPaid > 0.0 && $amountPaid < $total) $debtStatus = 'partial';
                if ($amountPaid >= $total) $debtStatus = 'paid';
                $stmtDebt->bind_param('iiddss', $transactionId, $customerId, $total, $amountPaid, $debtStatus, $notes);
                if (!$stmtDebt->execute()) throw new Exception('Failed to insert debt record.');
                $insertedDebtId = $this->db->insert_id;
                $stmtDebt->close();

                // If there was a payment made towards the debt (partial payment at sale time), record it
                if ($amountPaid > 0.0 && $amountPaid <= $total) {
                    $appliedAmountDebt = min($amountPaid, $total);
                    $stmtPay = $this->db->prepare('INSERT INTO payment_records (sales_debt_id, payment_date, payment_method_id, amount, amount_tendered, total_due, change_amount, received_by_user_id, notes, created_at) VALUES (?, NOW(), ?, ?, ?, ?, 0.00, ?, ?, NOW())');
                    if (!$stmtPay) throw new Exception('Failed to prepare payment record insert.');
                    $stmtPay->bind_param('iidddis', $insertedDebtId, $paymentMethodId, $appliedAmountDebt, $tenderedAmount, $total, $userId, $notes);
                    if (!$stmtPay->execute()) throw new Exception('Failed to insert payment record.');
                    $stmtPay->close();
                }
            }

            $stmtItem->close();
            $stmtUpdateStock->close();
            $stmtMovement->close();

            $this->db->commit();

            // Redirect to sales index with success (host-agnostic, correct TX id)
            $txId = $transactionId;
            header('Location: ../../sales/index.php?success=1&tx=' . $txId);
            exit;
        } catch (Exception $ex) {
            $this->db->rollback();
            $this->fail(['Failed to process sale: ' . $ex->getMessage()]);
            return;
        } finally {
            if (isset($stmtGetItem) && $stmtGetItem) $stmtGetItem->close();
        }
    }

    /**
     * Return recent sales for listing
     * @return array<int, array<string, mixed>>
     */
    public function getAll(int $limit = 100): array
    {
        $sql = "SELECT st.id, st.transaction_date, st.total_amount, st.status,
                       COALESCE(c.name, 'Walk-in Customer') AS customer_name,
                       pm.name AS payment_method_name
                FROM sales_transactions st
                LEFT JOIN customers c ON c.id = st.customer_id
                LEFT JOIN payment_methods pm ON pm.id = st.payment_method_id
                WHERE EXISTS (SELECT 1 FROM sales_transaction_items sti WHERE sti.sales_transaction_id = st.id)
                ORDER BY st.transaction_date DESC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
        $stmt->close();
        return $rows;
    }

    /**
     * Search sales by customer name, payment method name, or ID
     * @param string $term
     * @return array<int, array<string, mixed>>
     */
    public function search(string $term): array
    {
        $like = '%' . $this->db->real_escape_string($term) . '%';
        $maybeId = ctype_digit($term) ? (int)$term : 0;
        $sql = "SELECT st.id, st.transaction_date, st.total_amount, st.status,
                       COALESCE(c.name, 'Walk-in Customer') AS customer_name,
                       pm.name AS payment_method_name
                FROM sales_transactions st
                LEFT JOIN customers c ON c.id = st.customer_id
                LEFT JOIN payment_methods pm ON pm.id = st.payment_method_id
                WHERE (c.name LIKE ? OR pm.name LIKE ? OR st.id = ?)
                  AND EXISTS (SELECT 1 FROM sales_transaction_items sti WHERE sti.sales_transaction_id = st.id)
                ORDER BY st.transaction_date DESC
                LIMIT 200";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('ssi', $like, $like, $maybeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
        $stmt->close();
        return $rows;
    }

    private function fail(array $errors): void
    {
        // You can adapt this to set session flash and redirect back
        // For now, show a simple error page
        http_response_code(400);
        echo '<h3>Sale could not be processed</h3>';
        echo '<ul>';
        foreach ($errors as $err) echo '<li>' . htmlspecialchars($err) . '</li>';
        echo '</ul>';
    }
}
