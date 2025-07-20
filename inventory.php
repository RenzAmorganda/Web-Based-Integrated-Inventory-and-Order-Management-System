<?php
$status_options = ["OK", "Low", "Out of Stock"];
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 80%; margin-bottom: 20px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #f4f4f4; }
    tr:nth-child(even) { background: #fafafa; }
    form { margin-top: 20px; }
    label { margin-right: 8px; }
    input[type="text"], input[type="number"] { margin-right: 8px; }
    input[type="submit"] { background: #28a745; color: white; border: none; padding: 6px 12px; cursor: pointer; }
    input[type="submit"]:hover { background: #218838; }
    .actions a { margin-right: 8px; }
    .pagination a, .pagination strong { margin: 0 2px; padding:2px 6px; text-decoration:none; }
    .pagination strong { background: #28a745; color: #fff; border-radius: 3px; }
    .low-stock { background: #fff0f0; }
    th a { color: inherit; text-decoration: none; }
</style>';

// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "inventory_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- EXPORT TO CSV FEATURE ---
if (isset($_GET['download_csv'])) {
    $csv_search = isset($_GET['search']) ? $_GET['search'] : '';
    // Sorting for CSV
    $sortable_columns = ['id', 'name', 'quantity', 'status'];
    $sort = isset($_GET['sort']) && in_array($_GET['sort'], $sortable_columns) ? $_GET['sort'] : 'id';
    $order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

    if ($csv_search) {
        $sql = "SELECT * FROM products WHERE name LIKE ? OR status LIKE ? ORDER BY $sort $order";
        $stmt = $conn->prepare($sql);
        $like = "%$csv_search%";
        $stmt->bind_param("ss", $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql = "SELECT * FROM products ORDER BY $sort $order";
        $result = $conn->query($sql);
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=inventory_export.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Quantity', 'Status']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [$row['id'], $row['name'], $row['quantity'], $row['status']]);
    }
    fclose($output);
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM products WHERE id=$id");
    // Redirect to avoid repeated deletes on refresh
    header("Location: inventory.php");
    exit;
}

// Handle edit (prefill the form)
$edit_id = null;
$edit_product = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $result_edit = $conn->query("SELECT * FROM products WHERE id=$edit_id");
    $edit_product = $result_edit->fetch_assoc();
}

// Handle form submission for add/update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $name = $_POST['name'];
    $quantity = $_POST['quantity'];
    $status = $_POST['status'];

    if (!empty($name) && is_numeric($quantity) && in_array($status, $status_options)) {
        if ($id) {
            // Update existing
            $sql = "UPDATE products SET name=?, quantity=?, status=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisi", $name, $quantity, $status, $id);
            $stmt->execute();
            $stmt->close();
            echo "<p style='color:green;'>Product updated!</p>";
        } else {
            // Insert new
            $sql = "INSERT INTO products (name, quantity, status) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sis", $name, $quantity, $status);
            $stmt->execute();
            $stmt->close();
            echo "<p style='color:green;'>Product added!</p>";
        }
    } else {
        echo "<p style='color:red;'>Please enter valid data.</p>";
    }
    // Redirect to clear POST data and avoid resubmission
    header("Location: inventory.php");
    exit;
}

// --- SEARCH FEATURE START ---
// Handle search query
$search = '';
if (isset($_GET['search'])) {
    $search = $_GET['search'];
}

// --- PAGINATION START ---
$per_page = 5; // Products per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$start = ($page - 1) * $per_page;

// --- SORTING FEATURE ---
$sortable_columns = ['id', 'name', 'quantity', 'status'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $sortable_columns) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

// Count total products for pagination (respecting search)
if (!empty($search)) {
    $count_sql = "SELECT COUNT(*) FROM products WHERE name LIKE ? OR status LIKE ?";
    $stmt = $conn->prepare($count_sql);
    $like = "%$search%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $stmt->bind_result($total_products);
    $stmt->fetch();
    $stmt->close();
} else {
    $count_sql = "SELECT COUNT(*) FROM products";
    $result_count = $conn->query($count_sql);
    $total_row = $result_count->fetch_row();
    $total_products = $total_row[0];
}
$total_pages = ceil($total_products / $per_page);
// --- PAGINATION END ---

// Helper for sortable columns
function sort_link($label, $column, $sort, $order, $search, $page) {
    $next_order = ($sort == $column && $order == 'ASC') ? 'desc' : 'asc';
    $params = [];
    if ($search) $params['search'] = $search;
    if ($page) $params['page'] = $page;
    $params['sort'] = $column;
    $params['order'] = $next_order;
    $query = http_build_query($params);
    $arrow = '';
    if ($sort == $column) $arrow = $order === 'ASC' ? ' ▲' : ' ▼';
    return "<a href=\"?{$query}\">{$label}{$arrow}</a>";
}
?>
<!-- Download CSV Button -->
<form method="get" action="" style="margin-bottom:15px;">
    <input type="hidden" name="download_csv" value="1">
    <?php if ($search) { ?>
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
    <?php } ?>
    <?php if ($sort) { ?>
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
    <?php } ?>
    <?php if ($order) { ?>
        <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
    <?php } ?>
    <input type="submit" value="Export Inventory to CSV">
</form>
<!-- Search Form -->
<form method="get" action="">
    <input type="text" name="search" placeholder="Search by name or status" value="<?php echo htmlspecialchars($search); ?>">
    <input type="submit" value="Search">
    <?php if ($search) { echo '<a href="inventory.php" style="margin-left:10px;">Clear</a>'; } ?>
</form>
<?php

// Query to get products (with search & pagination & sorting)
if (!empty($search)) {
    $sql = "SELECT * FROM products WHERE name LIKE ? OR status LIKE ? ORDER BY $sort $order LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $like = "%$search%";
    $stmt->bind_param("ssii", $like, $like, $start, $per_page);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM products ORDER BY $sort $order LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $start, $per_page);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Display inventory list

echo "<h1>Inventory List</h1>";
echo "<table>";
echo "<tr>";
echo "<th>" . sort_link("ID", "id", $sort, $order, $search, $page) . "</th>";
echo "<th>" . sort_link("Name", "name", $sort, $order, $search, $page) . "</th>";
echo "<th>" . sort_link("Quantity", "quantity", $sort, $order, $search, $page) . "</th>";
echo "<th>" . sort_link("Status", "status", $sort, $order, $search, $page) . "</th>";
echo "<th>Actions</th>";
echo "</tr>";

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $low_stock = ($row['quantity'] <= 15);
        $row_class = $low_stock ? 'low-stock' : '';
        echo "<tr class='$row_class'>";
        echo "<td>".$row['id']."</td>";
        echo "<td>".$row['name']."</td>";
        echo "<td>".$row['quantity'];
        if ($low_stock) {
            echo " <span title='Low stock' style='color:red;'>⚠️Stock Low Or Empty</span>";
        }
        echo "</td>";
        echo "<td>".$row['status']."</td>";
        echo "<td class='actions'>"
            . "<a href='?edit=".$row['id']."";
        if ($search) { echo "&search=" . urlencode($search); }
        if ($page) { echo "&page=" . intval($page); }
        if ($sort) { echo "&sort=" . urlencode($sort); }
        if ($order) { echo "&order=" . urlencode(strtolower($order)); }
        echo "'>Edit</a>";
        echo "<a href='?delete=".$row['id']."";
        if ($search) { echo "&search=" . urlencode($search); }
        if ($page) { echo "&page=" . intval($page); }
        if ($sort) { echo "&sort=" . urlencode($sort); }
        if ($order) { echo "&order=" . urlencode(strtolower($order)); }
        echo "' onclick=\"return confirm('Are you sure?');\">Delete</a>";
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5'>No products found</td></tr>";
}
echo "</table>";

// Pagination links
echo "<div class='pagination'>";
if ($total_pages > 1) {
    for ($i = 1; $i <= $total_pages; $i++) {
        $params = $_GET;
        $params['page'] = $i;
        $query = http_build_query($params);
        if ($i == $page) {
            echo "<strong>$i</strong> ";
        } else {
            echo "<a href='?$query'>$i</a> ";
        }
    }
}
echo "</div>";

// Add/edit product form
?>
<h2><?php echo $edit_id ? "Edit Product" : "Add New Product"; ?></h2>
<form method="post" action="">
    <input type="hidden" name="id" value="<?php echo $edit_id ? $edit_product['id'] : ''; ?>">
    <label for="name">Product Name:</label>
    <input type="text" name="name" value="<?php echo $edit_id ? htmlspecialchars($edit_product['name']) : ''; ?>" required>
    <label for="quantity">Quantity:</label>
    <input type="number" name="quantity" min="0" value="<?php echo $edit_id ? htmlspecialchars($edit_product['quantity']) : ''; ?>" required>
    <label for="status">Status:</label>
    <select name="status" required>
        <option value="">Select status</option>
        <?php
        foreach ($status_options as $option) {
            $selected = ($edit_id && $edit_product['status'] == $option) ? 'selected' : '';
            echo "<option value=\"$option\" $selected>$option</option>";
        }
        // If adding new, keep the previously submitted value (from $_POST) if validation failed
        if (!$edit_id && isset($_POST['status'])) {
            foreach ($status_options as $option) {
                if ($_POST['status'] == $option) {
                    echo "<option value=\"$option\" selected>$option</option>";
                }
            }
        }
        ?>
    </select>
    <input type="submit" value="<?php echo $edit_id ? 'Update Product' : 'Add Product'; ?>">
</form>
<?php
$conn->close();
?>