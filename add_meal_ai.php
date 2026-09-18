<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
include 'includes/icon.php';
include 'ai_bridge.php';

$user_id = $_SESSION['user_id'];
$food_detected = null;
$detection_error = null;
$meal_added = false;

// Handle food image upload for CNN detection
if(isset($_POST['upload_image'])) {
    if(isset($_FILES['food_image']) && $_FILES['food_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['food_image']['name']);
        $target_path = $upload_dir . $file_name;
        
        if(move_uploaded_file($_FILES['food_image']['tmp_name'], $target_path)) {
            // Call CNN model to detect food
            global $ai;
            $result = $ai->detectFood($target_path);
            
            if(isset($result['success']) && $result['success']) {
                $food_detected = $result['best_match'];
            } elseif(isset($result['error'])) {
                // CNN couldn't identify - suggest manual entry
                $detection_error = $result['error'];
            } else {
                $detection_error = "Food image unclear. Please select manually.";
            }
        } else {
            $detection_error = "Failed to upload image.";
        }
    } else {
        $detection_error = "Please select an image to upload.";
    }
}

// Handle manual meal add
if(isset($_POST['add_meal'])) {
    $food = $_POST['food'];
    $quantity = $_POST['quantity'];
    
    $query = "INSERT INTO meals (user_id, food_name, quantity) VALUES ('$user_id', '$food', '$quantity')";
    if(mysqli_query($conn, $query)) {
        $meal_added = true;
    }
}

// Get all food items for dropdown
$food_query = "SELECT * FROM food_items ORDER BY food_name";
$food_result = mysqli_query($conn, $food_query);

// Get nutrition details if meal was just added or food detected
$nutrition_row = null;
if(isset($_POST['add_meal']) && isset($food)) {
    $nutrition_query = "SELECT * FROM food_items WHERE food_name='$food'";
    $nutrition_result = mysqli_query($conn, $nutrition_query);
    $nutrition_row = mysqli_fetch_assoc($nutrition_result);
} elseif($food_detected) {
    $nutrition_query = "SELECT * FROM food_items WHERE food_name LIKE '%$food_detected%'";
    $nutrition_result = mysqli_query($conn, $nutrition_query);
    $nutrition_row = mysqli_fetch_assoc($nutrition_result);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Meal - NutriVision AI</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .ai-badge {
            background: linear-gradient(135deg, #FF3D7F, #FFC93C);
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 8px;
        }
        .upload-area {
            border: 2px dashed #ECEFFC;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #FAFBFF;
            margin-bottom: 20px;
        }
        .upload-area:hover {
            border-color: var(--primary);
            background: #E4FFF1;
        }
        .upload-area.dragover {
            border-color: var(--primary);
            background: #E4FFF1;
        }
        .upload-area img {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 8px;
        }
        .detection-result {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .detection-success {
            background: #E4FFF1;
            border: 1px solid #00D66F;
            color: #00B85E;
        }
        .detection-error {
            background: #FFF6E0;
            border: 1px solid #FFB020;
            color: #8A5A00;
        }
        body.dark-mode .upload-area {
            background: rgba(25,27,58,0.7);
            border-color: rgba(255,255,255,0.14);
        }
        body.dark-mode .upload-area:hover {
            border-color: var(--primary);
        }
        body.dark-mode .detection-success {
            background: rgba(0,214,111,0.14);
            color: #00D66F;
        }
        body.dark-mode .detection-error {
            background: rgba(255,176,32,0.14);
            color: #FFC93C;
        }
        .meal-preview-container {
            background: linear-gradient(135deg, #FF3D7F, #FFC93C);
            border-radius: 15px;
            padding: 20px;
            color: white;
            margin: 15px 0;
        }
        @media (max-width: 600px) {
            .cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="navbar">
    <h2><?php echo nv_icon('logo', 24); ?><span class="brand-text">NutriVision AI</span></h2>
    <div>
        <span class="dark-toggle" onclick="toggleDarkMode()">🌙 Dark Mode</span>
        <a href="dashboard.php" class="btn" style="margin-left: 10px;">Dashboard</a>
    </div>
</div>

<div class="container">
    <h1>Add Meal <span class="ai-badge">🤖 AI Powered</span></h1>
    
    <?php if($meal_added): ?>
    <div class="alert alert-success" style="background: #E4FFF1; color: #00B85E; border: 1px solid #00D66F; padding: 15px; border-radius: 8px; margin: 10px 0;">
        ✅ Meal added successfully!
    </div>
    <?php endif; ?>

    <!-- CNN Food Detection Section -->
    <div class="card" style="margin: 20px 0;">
        <h2>📸 CNN Food Detection</h2>
        <p style="color: var(--gray); margin-bottom: 15px;">
            Upload a food photo and AI will detect it automatically
        </p>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="upload-area" id="uploadArea" onclick="document.getElementById('food_image').click()">
                <p style="font-size: 48px; margin: 0;">🍽️</p>
                <p><strong>Click to upload food photo</strong></p>
                <p style="color: var(--gray); font-size: 0.9rem;">or drag and drop</p>
                <img id="preview" style="display: none;">
                <input type="file" id="food_image" name="food_image" accept="image/*" style="display: none;" onchange="previewImage(event)">
            </div>
            
            <button type="submit" name="upload_image" class="btn" style="background: linear-gradient(135deg, #FF3D7F, #FFC93C);">
                🔍 Detect Food with AI
            </button>
        </form>

        <?php if($food_detected): ?>
        <div class="detection-result detection-success">
            <strong>✅ CNN Detected:</strong> <?php echo htmlspecialchars($food_detected); ?>
        </div>
        <?php endif; ?>
        
        <?php if($detection_error): ?>
        <div class="detection-result detection-error">
            <strong>⚠️ <?php echo htmlspecialchars($detection_error); ?></strong><br>
            <p style="margin-top: 5px;">Please select from the dropdown below.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Manual Entry Form -->
    <form method="POST">
        <h2>📝 Manual Entry</h2>
        
        <label>Select Food</label>
        <select name="food" id="foodSelect">
            <option value="">-- Select Food --</option>
            <?php 
            if($food_result && mysqli_num_rows($food_result) > 0):
                while($food = mysqli_fetch_assoc($food_result)):
                    $selected = ($food_detected && stripos($food['food_name'], $food_detected) !== false) ? 'selected' : '';
            ?>
            <option value="<?php echo $food['food_name']; ?>" <?php echo $selected; ?> 
                    data-calories="<?php echo $food['calories']; ?>"
                    data-protein="<?php echo $food['protein']; ?>"
                    data-carbs="<?php echo $food['carbs']; ?>"
                    data-fat="<?php echo $food['fat']; ?>">
                <?php echo $food['food_name']; ?> (<?php echo $food['calories']; ?> kcal)
            </option>
            <?php 
                endwhile;
            endif; 
            ?>
            <option value="Other">Other (Custom)</option>
        </select>

        <div id="customFoodInput" style="display: none;">
            <label>Custom Food Name</label>
            <input type="text" name="custom_food" placeholder="Enter food name">
        </div>

        <label>Quantity (servings)</label>
        <input type="number" name="quantity" value="1" min="0.5" step="0.5" required>

        <button type="submit" name="add_meal" class="btn">Add Meal</button>
    </form>

    <!-- Nutrition Details -->
    <?php if($nutrition_row): ?>
    <div class="card">
        <h2>📊 Nutrition Details</h2>
        <div class="cards" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
            <div class="card" style="background: #E4FFF1;">
                <h3>🔥 Calories</h3>
                <h1><?php echo $nutrition_row['calories']; ?> kcal</h1>
            </div>
            <div class="card" style="background: #E4F7FF;">
                <h3>💪 Protein</h3>
                <h1><?php echo $nutrition_row['protein']; ?> g</h1>
            </div>
            <div class="card" style="background: #FFF6E0;">
                <h3>🌾 Carbs</h3>
                <h1><?php echo $nutrition_row['carbs']; ?> g</h1>
            </div>
            <div class="card" style="background: #FFE9EE;">
                <h3>🥑 Fat</h3>
                <h1><?php echo $nutrition_row['fat']; ?> g</h1>
            </div>
            <div class="card" style="background: #ECEFFC;">
                <h3>🌿 Fiber</h3>
                <h1><?php echo $nutrition_row['fiber']; ?> g</h1>
            </div>
            <div class="card" style="background: #FFE9EE;">
                <h3>🍬 Sugar</h3>
                <h1><?php echo $nutrition_row['sugar']; ?> g</h1>
            </div>
        </div>
        
        <div style="margin-top: 15px; padding: 15px; background: var(--light-gray); border-radius: 8px;">
            <h4>🧪 Vitamins & Minerals</h4>
            <p><strong>Vitamins:</strong> <?php echo $nutrition_row['vitamins']; ?></p>
            <p><strong>Minerals:</strong> <?php echo $nutrition_row['minerals']; ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>💡 AI Smart Tip</h2>
        <p id="aiTip">Loading AI recommendation...</p>
    </div>
</div>

<script>
// Image preview
function previewImage(event) {
    const reader = new FileReader();
    const preview = document.getElementById('preview');
    reader.onload = function() {
        preview.src = reader.result;
        preview.style.display = 'block';
    }
    if(event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    }
}

// Drag and drop
const uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});
uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});
uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const files = e.dataTransfer.files;
    if(files.length > 0) {
        document.getElementById('food_image').files = files;
        previewImage({target: {files: files}});
    }
});

// Show custom food input
document.getElementById('foodSelect').addEventListener('change', function() {
    document.getElementById('customFoodInput').style.display = 
        this.value === 'Other' ? 'block' : 'none';
});

// Dark mode
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
}

// Load AI tip
fetch('http://127.0.0.1:5050/api/nlp/chat', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({message: 'Give me a tip for healthy eating'})
})
.then(res => res.json())
.then(data => {
    document.getElementById('aiTip').textContent = data.response || 'Eat balanced meals with protein, carbs, and vegetables!';
})
.catch(() => {
    document.getElementById('aiTip').textContent = 'Include protein in every meal and drink plenty of water!';
});
</script>
</body>
</html>