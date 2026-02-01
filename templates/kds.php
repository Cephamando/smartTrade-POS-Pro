<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KDS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #000; color: #fff; padding: 10px; }
        .ticket-card { border: 2px solid #333; background: #1a1a1a; min-height: 250px; }
        .ticket-header { background: #333; padding: 10px; font-weight: bold; display: flex; justify-content: space-between; }
        .ticket-body { padding: 10px; }
        .ticket-item { font-size: 1.2rem; border-bottom: 1px dashed #444; padding: 5px 0; }
        .timer { color: #ffc107; font-family: monospace; }
        .ticket-footer { padding: 10px; }
    </style>
</head>
<body>
    <div class="row g-3" id="ordersContainer">
        <div class="col-12 text-center mt-5 text-muted">
            <h3>Waiting for orders...</h3>
        </div>
    </div>
    
    <script>
        // Use standard AJAX to fetch orders (simplified for demo)
        // In real app, this would be updated via the fetch loop similar to Pickup screen
        // For now, I'm providing the layout structure.
    </script>
</body>
</html>
