<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/controller/customer/CustomerController.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;
$customerController = new CustomerController();

// Handle different actions
switch ($action) {
    case 'view':
        $customer = $customerController->getById($id);
        if (!$customer) {
            $_SESSION['error'] = 'Customer not found.';
            header('Location: customers.php');
            exit();
        }
        $pageTitle = 'View Customer';
        include __DIR__ . '/templates/header.php';
        include __DIR__ . '/ledger/customers/view.php';
        break;
        
    case 'add':
        $pageTitle = 'Add New Customer';
        include __DIR__ . '/templates/header.php';
        include __DIR__ . '/ledger/customers/add.php';
        break;
        
    case 'edit':
        $customer = $customerController->getById($id);
        if (!$customer) {
            $_SESSION['error'] = 'Customer not found.';
            header('Location: customers.php');
            exit();
        }
        $pageTitle = 'Edit Customer';
        include __DIR__ . '/templates/header.php';
        include __DIR__ . '/ledger/customers/edit.php';
        break;
        
    case 'delete':
        if ($customerController->delete($id)) {
            $_SESSION['success'] = 'Customer deleted successfully.';
        } else {
            $_SESSION['error'] = 'Failed to delete customer.';
        }
        header('Location: customers.php');
        exit();
        
    case 'save':
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'contact' => trim($_POST['contact'] ?? ''),
            'address' => trim($_POST['address'] ?? '')
        ];
        
        if (empty($data['name']) || empty($data['contact'])) {
            $_SESSION['error'] = 'Name and contact are required fields.';
            header('Location: customers.php?action=' . ($id ? 'edit&id=' . $id : 'add'));
            exit();
        }
        
        if ($id) {
            // Update existing customer
            if ($customerController->update($id, $data)) {
                $_SESSION['success'] = 'Customer updated successfully.';
                header('Location: customers.php?action=view&id=' . $id);
            } else {
                $_SESSION['error'] = 'Failed to update customer.';
                header('Location: customers.php?action=edit&id=' . $id);
            }
        } else {
            // Create new customer
            if ($customerController->create($data)) {
                $newId = $customerController->getLastInsertId();
                $_SESSION['success'] = 'Customer added successfully.';
                header('Location: customers.php?action=view&id=' . $newId);
            } else {
                $_SESSION['error'] = 'Failed to add customer.';
                header('Location: customers.php?action=add');
            }
        }
        exit();
        
    case 'list':
    default:
        $search = $_GET['search'] ?? '';
        if (!empty($search)) {
            $customers = $customerController->search($search);
        } else {
            $customers = $customerController->getAll();
        }
        $pageTitle = 'Customers';
        include __DIR__ . '/templates/header.php';
        include __DIR__ . '/ledger/customers/index.php';
        break;
}

include __DIR__ . '/templates/footer.php';
?>
