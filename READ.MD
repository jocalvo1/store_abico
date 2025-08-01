I'm here

Create a Ledger first with tabs of: 
 1. Customers /
   - id
   - name
   - contact
   - address
   - created_at
   - updated_at
 2. Suppliers / 
   - id
   - name
   - contact_person
   - contact_number
   - email
   - address
   - notes
   - created_at
   - updated_at
 3. Purchase Orders / 
   - id
   - supplier_id (suppliers.id)
   - po_number
   - order_date
   - status [pending, approved, cancelled, completed]
   - total amount
   - created_by_user_id (users.id)
   - notes
   - created_at
   - updated_at
 4. Purchase Orders Items / 
   - id
   - purchase_order_id (purchase_orders.id)
   - item_id (items.id)
   - quantity
   - unit_price
   - total_price
   - notes
   - created_at
 5. Deliveries /
   - id
   - purchase_order_id (purchase_orders.id)
   - delivery_date
   - status [pending, delivered, cancelled]
   - delivered_by
   - received_by_user_id (users.id)
   - cancel_reason
   - confirm_notes
   - created_at
   - updated_at
 6. Deliveries Items /
   - id
   - delivery_id (delivery.id)
   - purchase_order_items_id (purchase_orders.id)
   - item_id (items.id)
   - quantity
   - received_quantity
   - notes
   - created_at
 7. Items / 
   - id
   - name
   - description
   - sku
   - category
   - unit
   - unit_price
   - current_stock
   - reorder_level
   - created_at
   - updated_at
 8. Sales Transaction /
   - id
   - customer_id (customers.id)
   - transaction_date
   - payment_method_id (payment_methods.id)
   - total_amount
   - status [paid, debt, partial]
   - created_by_user_id (users.id)
   - notes
   - created_at
 9. Sales Transaction Items / 
   - id
   - sales_transaction_id (sales_transaction.id)
   - item_id (items.id)
   - quantity
   - unit_price
   - total_price
   - notes
 10. Sales Debt /
   - id
   - sales_transaction_id (sales_transaction.id)
   - customer_id (customers.id)
   - total_amount
   - amount_paid
   - balance_due
   - due_date
   - status [unpaid, paid, partial]
   - notes
   - created_at
   - updated_at
 11. Payment Records
   - id
   - sales_debt_id (sales_debt.id)
   - payment_date
   - payment_method_id (payment_methods.id)
   - amount
   - received_by_user_id (users.id)
   - notes
   - created_at
 12. Payment Methods / 
   - id
   - name
   - description
   - created_at

INSERT INTO payment_methods (name, description) VALUES
('Cash', 'Cash Payment at Store'),
('Online Transfer', 'Gcash, Paymaya, Bank Transfer'),
('Check', 'Payment through Check');

 13. Stock Movement /
   - id
   - item_id (items.id)
   - movement_type [in, out, adjustment]
   - quantity
   - reference_type [delivery, sales, return, adjustment]
   - reference_id 
   - notes
   - created_at
 14. Check Exchanges / 
   - id
   - customer_id (customers.id)
   - check_number
   - bank_name
   - check_date
   - amount
   - status [pending, cleared, bounced]
   - received_by_user_id (users.id)
   - notes
   - created_at
   - updated_at
 15. Users / 
   - id
   - name 
   - username
   - password
   - role
   - contact
   - created_at
   - updated_at

 NOTES * 

 Sa unit_price of an item, ang iya ka unit_price ni purchase_order_items is ang original nga price, meaning amo na danay ang gi agreehan nyo nga price. While ang unit_price naman ni deliveries_item, amo na ni ang price pag baligya mo sang mga item. In short, ang purchase_order_items is ang wholesale price, while ang iya ka deliveries_item is ang retail price.

 In order: 
 1. Users
 2. Suppliers
 3. Customers
 4. Items
 5. Purchase_orders
 6. Payment_methods
 7. Purchase_order_items
 8. Deliveries