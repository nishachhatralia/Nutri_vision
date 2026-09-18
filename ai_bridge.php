<?php
/**
 * AI Bridge - Connects PHP frontend to Python AI/ML backend
 * Makes HTTP requests to the Flask server running on port 5050
 */

class AIBridge {
    private $api_url = 'http://127.0.0.1:5050';
    private $timeout = 30;

    /**
     * Make HTTP POST request to AI backend
     */
    private function post($endpoint, $data = []) {
        $url = $this->api_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'Connection error: ' . $error,
                'ai_available' => false
            ];
        }
        
        $result = json_decode($response, true);
        if ($result === null) {
            return [
                'success' => false,
                'error' => 'Invalid response from AI backend',
                'ai_available' => false
            ];
        }
        
        $result['ai_available'] = true;
        return $result;
    }

    /**
     * 1. CNN - Detect food from image
     */
    public function detectFood($image_path) {
        if (!file_exists($image_path)) {
            return [
                'success' => false,
                'error' => 'Image file not found'
            ];
        }

        $url = $this->api_url . '/api/cnn/predict';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        
        // Create multipart request
        $curl_file = new CURLFile($image_path, mime_content_type($image_path), basename($image_path));
        $post_data = ['image' => $curl_file];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => 'CNN error: ' . $error, 'ai_available' => false];
        }
        
        $result = json_decode($response, true);
        $result['ai_available'] = ($result !== null);
        return $result ?: ['success' => false, 'error' => 'Invalid CNN response'];
    }

    /**
     * 2. LSTM - Generate personalized diet plan
     */
    public function generateDietPlan($goal = 'maintain_weight', $user_data = null, $preferences = null) {
        return $this->post('/api/lstm/diet-plan', [
            'goal' => $goal,
            'user_data' => $user_data,
            'preferences' => $preferences
        ]);
    }

    /**
     * 3. RNN - Analyze nutrition patterns
     */
    public function analyzeNutrition($meals_data = []) {
        return $this->post('/api/rnn/analyze', [
            'meals_data' => $meals_data
        ]);
    }

    /**
     * 4. GAN - Generate meal preview
     */
    public function getMealPreview($meal_type = 'lunch', $options = null) {
        return $this->post('/api/gan/preview', [
            'meal_type' => $meal_type,
            'options' => $options
        ]);
    }

    /**
     * 5. NLP - Chatbot response
     */
    public function chat($message) {
        return $this->post('/api/nlp/chat', [
            'message' => $message
        ]);
    }

    /**
     * Combined dashboard analysis
     */
    public function analyzeDashboard($meals_data, $goal = 'maintain_weight') {
        return $this->post('/api/analyze/user-dashboard', [
            'meals_data' => $meals_data,
            'goal' => $goal
        ]);
    }

    /**
     * Check if AI backend is running
     */
    public function isAvailable() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($http_code === 200);
    }

    /**
     * Get user meals data formatted for AI
     */
    public static function formatMealsForAI($meals_result) {
        $meals_data = [];
        if ($meals_result && mysqli_num_rows($meals_result) > 0) {
            while ($meal = mysqli_fetch_assoc($meals_result)) {
                $meals_data[] = [
                    'food_name' => $meal['food_name'],
                    'quantity' => (int)($meal['quantity'] ?? 1),
                    'meal_time' => $meal['meal_time'] ?? date('Y-m-d H:i:s'),
                    'calories' => (float)($meal['calories'] ?? 0),
                    'protein' => (float)($meal['protein'] ?? 0),
                    'carbs' => (float)($meal['carbs'] ?? 0),
                    'fat' => (float)($meal['fat'] ?? 0),
                    'fiber' => (float)($meal['fiber'] ?? 0),
                    'sugar' => (float)($meal['sugar'] ?? 0)
                ];
            }
        }
        return $meals_data;
    }
}

// Create global instance
$ai = new AIBridge();
?>