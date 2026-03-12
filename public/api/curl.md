curl -X POST http://localhost:8090/api/catch_online_order.php \
-H "Content-Type: application/json" \
-H "Authorization: Bearer pos_token_8f7d9a2b4c6e1mando99384" \
-d '{"external_order_id": "UBER-TAB-102", "customer_name": "Mando Online", "total_amount": 100.00, "items": [{"product_id": 72, "quantity": 3, "price": 150.00}]}'