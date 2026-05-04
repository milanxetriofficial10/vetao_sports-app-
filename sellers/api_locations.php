<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // POST: seller sends their GPS location
    case 'update_location':
        $seller_id = intval($_POST['seller_id'] ?? 0);
        $lat       = floatval($_POST['lat'] ?? 0);
        $lng       = floatval($_POST['lng'] ?? 0);
        $accuracy  = floatval($_POST['accuracy'] ?? 0);
        $speed     = floatval($_POST['speed'] ?? 0);
        $heading   = floatval($_POST['heading'] ?? 0);

        if (!$seller_id || !$lat || !$lng) {
            echo json_encode(['success' => false, 'msg' => 'Missing required fields']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO seller_locations (seller_id, lat, lng, accuracy, speed, heading) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('iddddd', $seller_id, $lat, $lng, $accuracy, $speed, $heading);
        $stmt->execute();

        // Update seller's current lat/lng
        $upd = $conn->prepare("UPDATE sellers SET lat=?, lng=? WHERE id=?");
        $upd->bind_param('ddi', $lat, $lng, $seller_id);
        $upd->execute();

        echo json_encode(['success' => true, 'logged_at' => date('Y-m-d H:i:s')]);
        break;

    // GET: admin fetches all sellers' latest position
    case 'live_positions':
        $sql = "
            SELECT s.id, s.name_en, s.name_np, s.phone, s.district_name, s.province,
                   s.lat, s.lng, s.status,
                   sl.logged_at as last_seen, sl.speed, sl.accuracy
            FROM sellers s
            LEFT JOIN seller_locations sl ON sl.id = (
                SELECT id FROM seller_locations
                WHERE seller_id = s.id
                ORDER BY logged_at DESC LIMIT 1
            )
            WHERE s.status = 'active'
        ";
        $result = $conn->query($sql);
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'sellers' => $data]);
        break;

    // GET: route history for one seller
    case 'route':
        $seller_id = intval($_GET['seller_id'] ?? 0);
        $hours     = intval($_GET['hours'] ?? 6);
        if (!$seller_id) { echo json_encode(['error' => 'Missing seller_id']); exit; }

        $stmt = $conn->prepare("
            SELECT lat, lng, logged_at, speed
            FROM seller_locations
            WHERE seller_id = ?
              AND logged_at >= NOW() - INTERVAL ? HOUR
            ORDER BY logged_at ASC
        ");
        $stmt->bind_param('ii', $seller_id, $hours);
        $stmt->execute();
        $result = $stmt->get_result();
        $points = [];
        while ($row = $result->fetch_assoc()) $points[] = $row;
        echo json_encode(['success' => true, 'seller_id' => $seller_id, 'points' => $points]);
        break;

    // GET: seller count
    case 'count':
        $r = $conn->query("SELECT COUNT(*) as c FROM sellers WHERE status='active'");
        $row = $r->fetch_assoc();
        echo json_encode(['count' => $row['c']]);
        break;

    // GET: all sellers list
    case 'sellers_list':
        $result = $conn->query("SELECT id, name_en, name_np, phone, district_name, province, status FROM sellers ORDER BY name_en");
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'sellers' => $data]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}

$conn->close();