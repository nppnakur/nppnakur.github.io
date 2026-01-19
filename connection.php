<?php
include "config.php";      // DB कनेक्शन

$where = [];
if (!empty($_GET['con'])) {
   $c = $conn->quote("%".$_GET['con']."%");
   $where[] = "con_no LIKE $c";
}
if (!empty($_GET['name'])) {
   $n = $conn->quote("%".$_GET['name']."%");
   $where[] = "owner_name LIKE $n";
}

$sql = "SELECT * FROM bills";
if ($where) { $sql .= " WHERE ".implode(" AND ", $where); }
$sql .= " ORDER BY con_no";
$stmt = $conn->query($sql);

echo '<table style="width:100%;border-collapse:collapse;box-shadow:0 2px 8px rgba(0,0,0,.1)">';
echo '<tr style="background:#007bff;color:#fff;text-align:center">
        <th>आईडी</th><th>Connection No</th><th>Owner</th><th>Mobile</th>
        <th>Year (2025-26)</th><th>Arrear (24-25)</th><th>Balance</th>
      </tr>';

$i = 1;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  echo '<tr style="text-align:center;border-bottom:1px solid #ddd">
          <td>NPPNCO'.str_pad($i,3,'0',STR_PAD_LEFT).'</td>
          <td>'.htmlspecialchars($row['con_no']).'</td>
          <td style="max-width:120px">'.htmlspecialchars($row['owner_name']).'</td>
          <td>'.htmlspecialchars($row['mobile']).'</td>
          <td>'.$row['current_amount_2025_26'].'</td>
          <td>'.$row['arrear_balance'].'</td>
          <td>'.$row['remaining_balance'].'</td>
        </tr>';
  $i++;
}
echo '</table>';
?>
