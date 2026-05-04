<?php
include "../databases/db.php";

// ===== DELETE SLIDER =====
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Optional: Delete image file from folder (recommended)
    $res = $conn->query("SELECT image FROM sliders WHERE id=$id");
    if ($row = $res->fetch_assoc()) {
        if (file_exists($row['image'])) {
            unlink($row['image']);
        }
    }
    $conn->query("DELETE FROM sliders WHERE id=$id");
    header("Location: add_slider.php");
    exit;
}

// ===== FETCH FOR EDIT =====
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM sliders WHERE id=$id");
    $editData = $res->fetch_assoc();
}

// ===== ADD / UPDATE SLIDER =====
if (isset($_POST['submit'])) {

    $title       = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $link        = $conn->real_escape_string($_POST['link']);

    // Main Image
    $imagePath = $editData['image'] ?? "";
    if (!empty($_FILES['image']['name'])) {
        $imageName = time() . "_" . $_FILES['image']['name'];
        $tmp = $_FILES['image']['tmp_name'];
        $imagePath = "../uploads/" . $imageName;
        move_uploaded_file($tmp, $imagePath);

        // Delete old image when updating (optional but recommended)
        if ($editData && !empty($editData['image']) && file_exists($editData['image'])) {
            unlink($editData['image']);
        }
    }

    // ===== UPDATE =====
    if (isset($_POST['id']) && $_POST['id'] != "") {
        $id = (int)$_POST['id'];

        $conn->query("UPDATE sliders SET 
            title='$title',
            description='$description',
            image='$imagePath',
            link='$link'
            WHERE id=$id");

        echo "<script>alert('✅ Slider Updated Successfully!'); window.location='add_slider.php';</script>";
    } 
    // ===== INSERT NEW =====
    else {
        $conn->query("INSERT INTO sliders (title, description, image, link) 
        VALUES ('$title', '$description', '$imagePath', '$link')");

        echo "<script>alert('✅ Slider Added Successfully!'); window.location='add_slider.php';</script>";
    }
}

// ===== FETCH ALL SLIDERS =====
$result = $conn->query("SELECT * FROM sliders ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin - Manage Sliders</title>
<style>
    body {
        font-family: sans-serif;
        background: #f1f5f9;
    }
    .container {
        max-width: 900px;
        margin: 30px auto;
        padding: 20px;
    }
    .form-box {
        width: 450px;
        margin: 0 auto 40px;
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    input, textarea {
        width: 100%;
        padding: 10px;
        margin: 8px 0;
        border: 1px solid #ccc;
        border-radius: 6px;
        box-sizing: border-box;
    }
    button {
        width: 100%;
        padding: 12px;
        background: #0f172a;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        margin-top: 10px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    th, td {
        padding: 12px;
        text-align: center;
        border-bottom: 1px solid #ddd;
    }
    th {
        background: #0f172a;
        color: white;
    }
    img {
        width: 80px;
        border-radius: 6px;
    }
    .btn {
        padding: 6px 12px;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        margin: 0 4px;
        font-size: 13px;
    }
    .edit { background: #16a34a; }
    .delete { background: #ef4444; }
    .action-cell { white-space: nowrap; }
</style>
</head>
<body>

<div class="container">

    <!-- Add / Edit Form -->
    <div class="form-box">
        <h2><?php echo $editData ? "✏️ Edit Slider" : "➕ Add New Slider"; ?></h2>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $editData['id'] ?? ''; ?>">

            <input type="text" name="title" placeholder="Slider Title" 
                   value="<?php echo $editData['title'] ?? ''; ?>" required>

            <textarea name="description" rows="3" placeholder="Description"><?php echo $editData['description'] ?? ''; ?></textarea>

            <input type="text" name="link" placeholder="Buy Link (optional)" 
                   value="<?php echo $editData['link'] ?? ''; ?>">

            <label>Main Image:</label>
            <input type="file" name="image" accept="image/*">

            <?php if ($editData && !empty($editData['image'])): ?>
                <p><small>Current Image:</small><br>
                <img src="<?php echo $editData['image']; ?>" width="100"></p>
            <?php endif; ?>

            <button type="submit" name="submit">
                <?php echo $editData ? "Update Slider" : "Add Slider"; ?>
            </button>
        </form>
    </div>

    <!-- Sliders List -->
    <h2 style="text-align:center; color:#1e3a8a; margin-bottom:15px;">All Sliders</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Image</th>
            <th>Title</th>
            <th>Description</th>
            <th>Link</th>
            <th>Action</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><img src="<?php echo $row['image']; ?>" alt=""></td>
            <td><?php echo htmlspecialchars($row['title']); ?></td>
            <td><?php echo htmlspecialchars(substr($row['description'], 0, 60)) . '...'; ?></td>
            <td>
                <?php if (!empty($row['link'])): ?>
                    <a href="<?php echo htmlspecialchars($row['link']); ?>" target="_blank">View Link</a>
                <?php else: ?>
                    <small style="color:#999;">No link</small>
                <?php endif; ?>
            </td>
            <td class="action-cell">
                <a href="?edit=<?php echo $row['id']; ?>" class="btn edit">Edit</a>
                <a href="?delete=<?php echo $row['id']; ?>" class="btn delete" 
                   onclick="return confirm('Are you sure you want to delete this slider?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

</div>

</body>
</html>