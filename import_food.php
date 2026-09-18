<?php
// import_food.php
// Loads food_data.json (if present) AND a hardcoded list of extra items
// (Chole, Paratha, etc.) into the food_items table. Safe to re-run.

include 'db.php';

function nv_insert_food($conn, $stmt, $food) {
    $stmt->bind_param(
        "sidddddssss",
        $food['food_name'],
        $food['calories'],
        $food['protein'],
        $food['carbs'],
        $food['fat'],
        $food['fiber'],
        $food['sugar'],
        $food['vitamins'],
        $food['minerals'],
        $food['category'],
        $food['image_url']
    );
    return $stmt->execute();
}

$stmt = $conn->prepare("
    INSERT INTO food_items
    (food_name, calories, protein, carbs, fat, fiber, sugar, vitamins, minerals, category, image_url)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        calories = VALUES(calories),
        protein = VALUES(protein),
        carbs = VALUES(carbs),
        fat = VALUES(fat),
        fiber = VALUES(fiber),
        sugar = VALUES(sugar),
        vitamins = VALUES(vitamins),
        minerals = VALUES(minerals),
        category = VALUES(category),
        image_url = VALUES(image_url)
");

$inserted = 0;

// --- Part 1: food_data.json, if it exists ---
$jsonFile = 'food_data.json';
if (file_exists($jsonFile)) {
    $data = file_get_contents($jsonFile);
    $foods = json_decode($data, true);
    if ($foods !== null) {
        foreach ($foods as $food) {
            if (nv_insert_food($conn, $stmt, $food)) {
                $inserted++;
            } else {
                echo "Failed to insert {$food['food_name']}: " . $stmt->error . "<br>";
            }
        }
    } else {
        echo "Warning: could not decode food_data.json — skipped.<br>";
    }
} else {
    echo "Note: food_data.json not found — skipping that step.<br>";
}

// --- Part 2: extra hardcoded items (Chole, Paratha, etc.) ---
$extra_foods = [
    ['food_name' => 'Aloo Paratha',        'calories' => 260, 'protein' => 6,  'carbs' => 36, 'fat' => 10, 'fiber' => 3, 'sugar' => 2,  'vitamins' => 'Vitamin C, B6',    'minerals' => 'Iron, Potassium',      'category' => 'Breakfast',    'image_url' => ''],
    ['food_name' => 'Plain Paratha',       'calories' => 210, 'protein' => 4,  'carbs' => 30, 'fat' => 8,  'fiber' => 2, 'sugar' => 1,  'vitamins' => 'B Vitamins',       'minerals' => 'Iron',                 'category' => 'Breakfast',    'image_url' => ''],
    ['food_name' => 'Chole Masala',        'calories' => 210, 'protein' => 9,  'carbs' => 27, 'fat' => 7,  'fiber' => 8, 'sugar' => 4,  'vitamins' => 'Vitamin C, Folate','minerals' => 'Iron, Magnesium',      'category' => 'Pulse',        'image_url' => ''],
    ['food_name' => 'Rajma Masala',        'calories' => 200, 'protein' => 8,  'carbs' => 30, 'fat' => 5,  'fiber' => 9, 'sugar' => 3,  'vitamins' => 'Folate, Vitamin B1','minerals' => 'Iron, Potassium',     'category' => 'Pulse',        'image_url' => ''],
    ['food_name' => 'Dal Makhani',         'calories' => 280, 'protein' => 10, 'carbs' => 26, 'fat' => 15, 'fiber' => 7, 'sugar' => 3,  'vitamins' => 'Vitamin A, Folate','minerals' => 'Iron, Calcium',       'category' => 'Pulse',        'image_url' => ''],
    ['food_name' => 'Palak Dal',           'calories' => 160, 'protein' => 8,  'carbs' => 22, 'fat' => 4,  'fiber' => 6, 'sugar' => 2,  'vitamins' => 'Vitamin A, C, K',  'minerals' => 'Iron, Calcium',        'category' => 'Pulse',        'image_url' => ''],
    ['food_name' => 'Pav Bhaji',           'calories' => 400, 'protein' => 8,  'carbs' => 55, 'fat' => 16, 'fiber' => 6, 'sugar' => 8,  'vitamins' => 'Vitamin C, A',     'minerals' => 'Iron, Potassium',      'category' => 'Street Food',  'image_url' => ''],
    ['food_name' => 'Misal Pav',           'calories' => 330, 'protein' => 12, 'carbs' => 40, 'fat' => 12, 'fiber' => 9, 'sugar' => 5,  'vitamins' => 'Vitamin C, Folate','minerals' => 'Iron, Magnesium',     'category' => 'Street Food',  'image_url' => ''],
    ['food_name' => 'Kachori',             'calories' => 260, 'protein' => 5,  'carbs' => 28, 'fat' => 14, 'fiber' => 3, 'sugar' => 1,  'vitamins' => 'B Vitamins',       'minerals' => 'Iron',                 'category' => 'Street Food',  'image_url' => ''],
    ['food_name' => 'Vegetable Pulao',     'calories' => 280, 'protein' => 6,  'carbs' => 48, 'fat' => 7,  'fiber' => 4, 'sugar' => 3,  'vitamins' => 'Vitamin A, C',     'minerals' => 'Iron, Potassium',      'category' => 'Rice Dish',    'image_url' => ''],
    ['food_name' => 'Jeera Rice',          'calories' => 240, 'protein' => 4,  'carbs' => 46, 'fat' => 5,  'fiber' => 1, 'sugar' => 0,  'vitamins' => 'B Vitamins',       'minerals' => 'Iron',                 'category' => 'Rice Dish',    'image_url' => ''],
    ['food_name' => 'Baingan Bharta',      'calories' => 120, 'protein' => 3,  'carbs' => 12, 'fat' => 7,  'fiber' => 5, 'sugar' => 4,  'vitamins' => 'Vitamin C, B6',    'minerals' => 'Potassium, Manganese', 'category' => 'Vegetable',    'image_url' => ''],
    ['food_name' => 'Bhindi Masala',       'calories' => 110, 'protein' => 3,  'carbs' => 10, 'fat' => 6,  'fiber' => 4, 'sugar' => 3,  'vitamins' => 'Vitamin C, K',     'minerals' => 'Magnesium, Potassium', 'category' => 'Vegetable',    'image_url' => ''],
    ['food_name' => 'Kadhi',               'calories' => 150, 'protein' => 5,  'carbs' => 14, 'fat' => 8,  'fiber' => 1, 'sugar' => 5,  'vitamins' => 'Vitamin B12, D',   'minerals' => 'Calcium',              'category' => 'Dairy',        'image_url' => ''],
    ['food_name' => 'Malai Kofta',         'calories' => 320, 'protein' => 8,  'carbs' => 22, 'fat' => 22, 'fiber' => 3, 'sugar' => 6,  'vitamins' => 'Vitamin A, C',     'minerals' => 'Calcium, Iron',        'category' => 'Protein',      'image_url' => ''],
    ['food_name' => 'Paneer Bhurji',       'calories' => 250, 'protein' => 15, 'carbs' => 6,  'fat' => 19, 'fiber' => 1, 'sugar' => 3,  'vitamins' => 'Vitamin A, B12',   'minerals' => 'Calcium, Phosphorus',  'category' => 'Protein',      'image_url' => ''],
    ['food_name' => 'Moong Dal Cheela',    'calories' => 180, 'protein' => 10, 'carbs' => 22, 'fat' => 5,  'fiber' => 5, 'sugar' => 2,  'vitamins' => 'Folate, B Vitamins','minerals' => 'Iron, Magnesium',     'category' => 'Breakfast',    'image_url' => ''],
    ['food_name' => 'Besan Chilla',        'calories' => 190, 'protein' => 9,  'carbs' => 20, 'fat' => 8,  'fiber' => 4, 'sugar' => 2,  'vitamins' => 'Folate, B6',       'minerals' => 'Iron, Magnesium',      'category' => 'Breakfast',    'image_url' => ''],
    ['food_name' => 'Sprouts Salad',       'calories' => 120, 'protein' => 8,  'carbs' => 18, 'fat' => 2,  'fiber' => 6, 'sugar' => 3,  'vitamins' => 'Vitamin C, K',     'minerals' => 'Iron, Potassium',      'category' => 'Snack',        'image_url' => ''],
    ['food_name' => 'Vegetable Sandwich',  'calories' => 220, 'protein' => 6,  'carbs' => 32, 'fat' => 7,  'fiber' => 3, 'sugar' => 4,  'vitamins' => 'Vitamin C, A',     'minerals' => 'Iron, Calcium',        'category' => 'Snack',        'image_url' => ''],
    ['food_name' => 'Vegetable Cutlet',    'calories' => 180, 'protein' => 4,  'carbs' => 22, 'fat' => 8,  'fiber' => 3, 'sugar' => 2,  'vitamins' => 'Vitamin A, C',     'minerals' => 'Iron, Potassium',      'category' => 'Snack',        'image_url' => ''],
    ['food_name' => 'Coconut Chutney',     'calories' => 90,  'protein' => 2,  'carbs' => 4,  'fat' => 8,  'fiber' => 2, 'sugar' => 1,  'vitamins' => 'Vitamin C, B6',    'minerals' => 'Manganese, Copper',    'category' => 'South Indian', 'image_url' => ''],
    ['food_name' => 'Roti with Ghee',      'calories' => 130, 'protein' => 3,  'carbs' => 20, 'fat' => 4,  'fiber' => 2, 'sugar' => 0,  'vitamins' => 'Vitamin A',        'minerals' => 'Iron',                 'category' => 'Grains',       'image_url' => ''],
    ['food_name' => 'Gulab Jamun',         'calories' => 150, 'protein' => 2,  'carbs' => 22, 'fat' => 6,  'fiber' => 0, 'sugar' => 18, 'vitamins' => 'Vitamin A',        'minerals' => 'Calcium',              'category' => 'Indian Sweet', 'image_url' => ''],
    ['food_name' => 'Kheer',               'calories' => 210, 'protein' => 5,  'carbs' => 32, 'fat' => 7,  'fiber' => 0, 'sugar' => 20, 'vitamins' => 'Vitamin A, D',     'minerals' => 'Calcium',              'category' => 'Indian Sweet', 'image_url' => ''],
];

foreach ($extra_foods as $food) {
    if (nv_insert_food($conn, $stmt, $food)) {
        $inserted++;
    } else {
        echo "Failed to insert {$food['food_name']}: " . $stmt->error . "<br>";
    }
}

$stmt->close();

echo "<hr>Import completed. $inserted records inserted/updated.";
?>