"""
LSTM - Diet Plan Prediction
Learns from user's meal timings, eating habits, sleep patterns, weight changes,
and workout consistency to generate smarter diet recommendations
"""

import numpy as np
import pandas as pd
import json
import sys
import os
from tensorflow.keras.models import Sequential, load_model
from tensorflow.keras.layers import LSTM, Dense, Dropout
from tensorflow.keras.preprocessing.sequence import pad_sequences
from sklearn.preprocessing import MinMaxScaler
import pickle

MODEL_PATH = os.path.join(os.path.dirname(__file__), 'models', 'lstm_diet_planner.h5')
SCALER_PATH = os.path.join(os.path.dirname(__file__), 'models', 'lstm_scaler.pkl')

# Default meal recommendations based on goals
MEAL_PLANS = {
    'weight_loss': {
        'breakfast': {
            'options': ['Oats with fruits', 'Egg whites with toast', 'Smoothie bowl'],
            'calories': 300,
            'protein': 20,
            'carbs': 35,
            'fat': 8
        },
        'lunch': {
            'options': ['Grilled chicken salad', 'Dal with small roti', 'Paneer wrap'],
            'calories': 400,
            'protein': 30,
            'carbs': 40,
            'fat': 10
        },
        'dinner': {
            'options': ['Soup with grilled veggies', 'Fish with steamed vegetables', 'Light paneer tikka'],
            'calories': 350,
            'protein': 25,
            'carbs': 25,
            'fat': 12
        },
        'snacks': {
            'options': ['Nuts (handful)', 'Fruit', 'Yogurt'],
            'calories': 150,
            'protein': 8,
            'carbs': 15,
            'fat': 5
        }
    },
    'muscle_gain': {
        'breakfast': {
            'options': ['Eggs with whole wheat toast', 'Protein pancakes', 'Peanut butter sandwich'],
            'calories': 500,
            'protein': 35,
            'carbs': 50,
            'fat': 15
        },
        'lunch': {
            'options': ['Chicken breast with rice', 'Fish with potatoes', 'Lentil curry with paneer'],
            'calories': 600,
            'protein': 40,
            'carbs': 60,
            'fat': 18
        },
        'dinner': {
            'options': ['Steak with vegetables', 'Egg curry with brown rice', 'Tofu stir-fry'],
            'calories': 550,
            'protein': 38,
            'carbs': 45,
            'fat': 20
        },
        'snacks': {
            'options': ['Protein shake', 'Mixed nuts with seeds', 'Cottage cheese'],
            'calories': 250,
            'protein': 20,
            'carbs': 10,
            'fat': 12
        }
    },
    'maintain_weight': {
        'breakfast': {
            'options': ['Paratha with curd', 'Vegetable poha', 'Idli with sambhar'],
            'calories': 400,
            'protein': 15,
            'carbs': 50,
            'fat': 12
        },
        'lunch': {
            'options': ['Full meal with roti-rice', 'Chicken curry with rice', 'Vegetable biryani'],
            'calories': 550,
            'protein': 25,
            'carbs': 60,
            'fat': 18
        },
        'dinner': {
            'options': ['Mixed vegetable curry', 'Grilled fish with salad', 'Paneer curry with roti'],
            'calories': 450,
            'protein': 22,
            'carbs': 45,
            'fat': 15
        },
        'snacks': {
            'options': ['Fruit chaat', 'Roasted chana', 'Smoothie'],
            'calories': 180,
            'protein': 8,
            'carbs': 25,
            'fat': 5
        }
    }
}


def create_lstm_model(input_shape):
    """Create an LSTM neural network for sequence prediction"""
    model = Sequential([
        LSTM(64, return_sequences=True, input_shape=input_shape),
        Dropout(0.2),
        LSTM(32, return_sequences=False),
        Dropout(0.2),
        Dense(16, activation='relu'),
        Dense(1, activation='linear')
    ])
    model.compile(optimizer='adam', loss='mse', metrics=['mae'])
    return model


def prepare_diet_sequences(user_data):
    """
    Convert user history into sequences for LSTM training
    user_data: list of dicts with keys - meal_time, calories, protein, carbs, fat, weight, workout_duration
    """
    df = pd.DataFrame(user_data)

    if len(df) < 2:
        return None, None

    features = ['calories', 'protein', 'carbs', 'fat', 'weight', 'workout_duration', 'hour_of_day']

    if 'hour_of_day' not in df.columns:
        # Extract hour from meal_time strings like "22:30:00" or "2024-01-01 22:30:00"
        df['hour_of_day'] = df['meal_time'].apply(
            lambda x: int(str(x).split(' ')[-1].split(':')[0]) if ':' in str(x) else 12
        )

    # Handle missing columns
    for col in features:
        if col not in df.columns:
            df[col] = 0

    # Normalize
    scaler = MinMaxScaler()
    scaled_data = scaler.fit_transform(df[features])

    # Create sequences
    seq_length = min(5, len(scaled_data) - 1)
    X, y = [], []
    for i in range(seq_length, len(scaled_data)):
        X.append(scaled_data[i - seq_length:i])
        y.append(scaled_data[i, 0])  # Predict next calorie value

    return np.array(X), np.array(y), scaler


def train_or_load_lstm(user_data=None):
    """Train LSTM on user data or return pre-trained model"""
    if user_data and len(user_data) >= 3:
        result = prepare_diet_sequences(user_data)
        if result[0] is not None:
            X, y, scaler = result
            if len(X) > 0:
                model = create_lstm_model((X.shape[1], X.shape[2]))
                model.fit(X, y, epochs=20, batch_size=4, verbose=0)

                # Save model and scaler
                os.makedirs(os.path.dirname(MODEL_PATH), exist_ok=True)
                model.save(MODEL_PATH)
                with open(SCALER_PATH, 'wb') as f:
                    pickle.dump(scaler, f)

                return model, scaler

    # Return default model if no user data
    return None, None


def generate_diet_plan(goal='maintain_weight', user_data=None, meal_timing_preferences=None):
    """
    Generate a personalized diet plan based on user goal, history and preferences.
    LSTM adjusts recommendations based on learned patterns.
    """
    goal_key = goal.lower().replace(' ', '_')
    if goal_key not in MEAL_PLANS:
        goal_key = 'maintain_weight'

    plan = MEAL_PLANS[goal_key]

    # If user data available, adjust plan using LSTM
    lstm_adjustments = {}
    if user_data and len(user_data) >= 3:
        model, scaler = train_or_load_lstm(user_data)

        if model and scaler and user_data:
            # Get last sequence for prediction
            df = pd.DataFrame(user_data[-5:])
            if 'hour_of_day' not in df.columns:
                df['hour_of_day'] = df['meal_time'].apply(
                    lambda x: int(str(x).split(' ')[-1].split(':')[0]) if ':' in str(x) else 12
                )

            features = ['calories', 'protein', 'carbs', 'fat', 'weight', 'workout_duration', 'hour_of_day']
            for col in features:
                if col not in df.columns:
                    df[col] = 0

            if len(df) >= 2:
                scaled = scaler.transform(df[features])
                seq = np.array([scaled[-min(5, len(scaled)):]])
                if len(seq[0]) >= 2:
                    predicted = model.predict(seq, verbose=0)
                    lstm_adjustments['predicted_calorie_trend'] = float(predicted[0][0])

    # Adjust based on meal timings
    if meal_timing_preferences:
        late_meals = meal_timing_preferences.get('late_meals', 0)
        avg_dinner_time = meal_timing_preferences.get('avg_dinner_hour', 20)

        if late_meals > 3 or avg_dinner_time > 21:
            # Lighter dinner recommendation
            plan['dinner'] = {
                'options': ['Light soup', 'Small salad with protein', 'Steamed vegetables'],
                'calories': int(plan['dinner']['calories'] * 0.7),
                'protein': int(plan['dinner']['protein'] * 0.8),
                'carbs': int(plan['dinner']['carbs'] * 0.5),
                'fat': int(plan['dinner']['fat'] * 0.6)
            }

    # Generate personalized recommendation text
    recommendation = f"Based on your goal '{goal}', here's your personalized diet plan:"

    return {
        'success': True,
        'goal': goal,
        'recommendation': recommendation,
        'meal_plan': plan,
        'lstm_adjustments': lstm_adjustments,
        'daily_totals': {
            'calories': sum(m['calories'] for m in plan.values()),
            'protein': sum(m['protein'] for m in plan.values()),
            'carbs': sum(m['carbs'] for m in plan.values()),
            'fat': sum(m['fat'] for m in plan.values())
        },
        'tip': get_tip_based_on_goal(goal_key, meal_timing_preferences)
    }


def get_tip_based_on_goal(goal_key, preferences):
    """Generate smart tip based on goal and eating patterns"""
    tips = {
        'weight_loss': [
            "Avoid late-night snacking for better results",
            "Include protein in every meal to stay full longer",
            "Drink water before meals to reduce appetite"
        ],
        'muscle_gain': [
            "Eat protein within 30 minutes post-workout",
            "Include healthy fats for hormone function",
            "Space meals 3-4 hours apart for optimal absorption"
        ],
        'maintain_weight': [
            "Balance your plate: 50% veggies, 25% protein, 25% carbs",
            "Stay consistent with meal timings",
            "Listen to hunger cues - eat when hungry, stop when full"
        ]
    }

    goal_tips = tips.get(goal_key, tips['maintain_weight'])

    if preferences and preferences.get('late_meals', 0) > 3:
        goal_tips.insert(0, "Lighter dinner recommended as you tend to eat late.")

    return np.random.choice(goal_tips)


def get_diet_plan_from_php(user_id, goal, user_data_json, preferences_json):
    """Main entry point called from PHP"""
    user_data = json.loads(user_data_json) if user_data_json else None
    preferences = json.loads(preferences_json) if preferences_json else None
    result = generate_diet_plan(goal, user_data, preferences)
    return json.dumps(result)


if __name__ == "__main__":
    if len(sys.argv) > 1:
        # Called from PHP with arguments
        user_data = json.loads(sys.argv[1]) if len(sys.argv) > 1 and sys.argv[1] != 'null' else None
        preferences = json.loads(sys.argv[2]) if len(sys.argv) > 2 and sys.argv[2] != 'null' else None
        goal = sys.argv[3] if len(sys.argv) > 3 else 'maintain_weight'

        result = generate_diet_plan(goal, user_data, preferences)
        print(json.dumps(result, indent=2))
    else:
        # Demo mode
        sample_data = [
            {"meal_time": "2024-01-01 08:00:00", "calories": 400, "protein": 20, "carbs": 50, "fat": 10, "weight": 75, "workout_duration": 30},
            {"meal_time": "2024-01-01 13:00:00", "calories": 550, "protein": 25, "carbs": 60, "fat": 18, "weight": 75, "workout_duration": 0},
            {"meal_time": "2024-01-01 22:00:00", "calories": 600, "protein": 20, "carbs": 55, "fat": 22, "weight": 75, "workout_duration": 0},
            {"meal_time": "2024-01-02 08:30:00", "calories": 380, "protein": 18, "carbs": 48, "fat": 9, "weight": 74.8, "workout_duration": 45},
            {"meal_time": "2024-01-02 23:00:00", "calories": 700, "protein": 25, "carbs": 65, "fat": 25, "weight": 74.8, "workout_duration": 0},
        ]
        prefs = {"late_meals": 4, "avg_dinner_hour": 22}
        result = generate_diet_plan('weight_loss', sample_data, prefs)
        print(json.dumps(result, indent=2))