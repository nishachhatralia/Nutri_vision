"""
Flask API Server - Bridges PHP frontend with all AI/ML models
Runs as a separate service on port 5050
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import json
import sys
import os

# Add the ai_backend directory to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

app = Flask(__name__)
CORS(app)  # Allow cross-origin requests from PHP

# Lazy-loaded model instances
cnn_model = None
lstm_model = None
lstm_scaler = None


def lazy_load_cnn():
    """Lazy load CNN model to save memory"""
    global cnn_model
    if cnn_model is None:
        from cnn_food_detection import load_model
        try:
            cnn_model = load_model()
            print("CNN model loaded successfully")
        except Exception as e:
            print(f"Error loading CNN model: {e}")
            cnn_model = "error"
    return cnn_model


def lazy_load_lstm():
    """Lazy load LSTM components"""
    global lstm_model, lstm_scaler
    import os
    import pickle
    from lstm_diet_planner import MODEL_PATH, SCALER_PATH
    from tensorflow.keras.models import load_model

    if lstm_model is None:
        if os.path.exists(MODEL_PATH):
            try:
                lstm_model = load_model(MODEL_PATH)
                with open(SCALER_PATH, 'rb') as f:
                    lstm_scaler = pickle.load(f)
                print("LSTM model loaded successfully")
            except Exception as e:
                print(f"Error loading LSTM model: {e}")
                lstm_model = "error"
        else:
            lstm_model = "not_trained"
    return lstm_model, lstm_scaler


# ==================== ROUTES ====================

@app.route('/')
def index():
    """Health check"""
    return jsonify({
        'status': 'online',
        'service': 'NutriVision AI Backend',
        'models': ['CNN (Food Detection)', 'LSTM (Diet Planning)', 'RNN (Nutrition Analysis)', 'GAN (Meal Preview)', 'NLP (Chatbot)'],
        'version': '1.0.0'
    })


@app.route('/api/cnn/predict', methods=['POST'])
def cnn_predict():
    """
    CNN - Food Detection from Image
    POST: multipart/form-data with 'image' file
    """
    from cnn_food_detection import predict_food_from_bytes

    if 'image' not in request.files:
        return jsonify({'error': 'No image file provided', 'success': False}), 400

    file = request.files['image']
    if file.filename == '':
        return jsonify({'error': 'No image selected', 'success': False}), 400

    try:
        image_bytes = file.read()
        model = lazy_load_cnn()
        if model == "error":
            return jsonify({'error': 'CNN model failed to load', 'success': False}), 500

        result = predict_food_from_bytes(image_bytes, model)
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e), 'success': False}), 500


@app.route('/api/lstm/diet-plan', methods=['POST'])
def lstm_diet_plan():
    """
    LSTM - Generate Diet Plan
    POST JSON: { goal: string, user_data: array, preferences: object }
    """
    from lstm_diet_planner import generate_diet_plan

    data = request.get_json()
    if not data:
        return jsonify({'error': 'No data provided', 'success': False}), 400

    try:
        goal = data.get('goal', 'maintain_weight')
        user_data = data.get('user_data', None)
        preferences = data.get('preferences', None)

        result = generate_diet_plan(goal, user_data, preferences)
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e), 'success': False}), 500


@app.route('/api/rnn/analyze', methods=['POST'])
def rnn_analyze():
    """
    RNN - Analyze Nutrition Patterns
    POST JSON: { meals_data: array }
    """
    from rnn_nutrition_analysis import NutritionPatternAnalyzer

    data = request.get_json()
    if not data:
        return jsonify({'error': 'No meal data provided', 'success': False}), 400

    try:
        meals_data = data.get('meals_data', [])
        analyzer = NutritionPatternAnalyzer()
        result = analyzer.analyze_meal_history(meals_data)
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e), 'success': False}), 500


@app.route('/api/gan/preview', methods=['POST'])
def gan_preview():
    """
    GAN - Generate Meal Preview
    POST JSON: { meal_type: string, options: object }
    """
    from gan_meal_preview import generate_meal_preview, generate_meal_preview_html

    data = request.get_json()
    if not data:
        return jsonify({'error': 'No data provided', 'success': False}), 400

    try:
        meal_type = data.get('meal_type', 'lunch')
        options = data.get('options', None)

        preview = generate_meal_preview(meal_type, options)
        html = generate_meal_preview_html(preview)
        return jsonify({
            'preview_data': preview,
            'html': html,
            'success': True
        })
    except Exception as e:
        return jsonify({'error': str(e), 'success': False}), 500


@app.route('/api/nlp/chat', methods=['POST'])
def nlp_chat():
    """
    NLP - Chatbot Response
    POST JSON: { message: string }
    """
    from nlp_chatbot import chatbot_response

    data = request.get_json()
    if not data:
        return jsonify({'error': 'No message provided', 'success': False}), 400

    try:
        message = data.get('message', '')
        response = chatbot_response(message)
        return jsonify({
            'message': message,
            'response': response,
            'success': True
        })
    except Exception as e:
        return jsonify({'error': str(e), 'success': False}), 500


@app.route('/api/analyze/user-dashboard', methods=['POST'])
def analyze_dashboard():
    """
    Combined analysis for dashboard - runs RNN analysis on user data
    """
    from rnn_nutrition_analysis import NutritionPatternAnalyzer

    data = request.get_json()
    if not data:
        return jsonify({'error': 'No data provided', 'success': False}), 400

    try:
        meals_data = data.get('meals_data', [])
        goal = data.get('goal', 'maintain_weight')

        # Run RNN analysis
        analyzer = NutritionPatternAnalyzer()
        analysis = analyzer.analyze_meal_history(meals_data)

        # Add calorie trend
        daily_calories = [s['calories'] for s in analysis.get('daily_summary', {}).values()]
        if daily_calories:
            analysis['avg_daily_calories'] = sum(daily_calories) / len(daily_calories)
            if analysis['avg_daily_calories'] >= 3:
                analysis['calorie_trend'] = "increasing" if daily_calories[-1] > daily_calories[0] else "decreasing" if daily_calories[-1] < daily_calories[0] else "stable"
        else:
            analysis['avg_daily_calories'] = 0

        return jsonify({
            'success': True,
            'analysis': analysis
        })
    except Exception as e:
        return jsonify({'error': str(e), 'success': False}), 500


if __name__ == '__main__':
    print("=" * 60)
    print("  NutriVision AI - Backend Server")
    print("  Models: CNN, LSTM, RNN, GAN, NLP")
    print("=" * 60)
    print(f"\nStarting server on http://0.0.0.0:5050")
    print("Press Ctrl+C to stop\n")

    # Download NLTK data
    try:
        import nltk
        nltk.download('punkt', quiet=True)
        nltk.download('stopwords', quiet=True)
    except:
        pass

    app.run(host='0.0.0.0', port=5050, debug=False, threaded=True)