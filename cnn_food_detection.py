"""
CNN - Food Detection Model
Uses MobileNetV2 pre-trained on ImageNet to identify food items from images
"""

import os
import numpy as np
import json
from tensorflow.keras.applications import MobileNetV2
from tensorflow.keras.applications.mobilenet_v2 import preprocess_input, decode_predictions
from tensorflow.keras.preprocessing import image
import sys

# Food categories that MobileNetV2 can recognize (subset relevant to Indian/Western food)
FOOD_CLASSES = [
    'pizza', 'burger', 'sandwich', 'hotdog', 'burrito', 'taco', 'nachos',
    'french_fries', 'pasta', 'spaghetti', 'steak', 'chicken_breast', 'drumstick',
    'egg', 'omelette', 'pancake', 'waffle', 'donut', 'cake', 'cookie', 'ice_cream',
    'apple', 'banana', 'orange', 'grape', 'watermelon', 'strawberry', 'pineapple',
    'salad', 'soup', 'bread', 'rice', 'roti', 'naan', 'chapati', 'curry',
    'samosa', 'pakora', 'dosa', 'idli', 'vada', 'paneer', 'dal', 'biryani',
    'pulao', 'kebabs', 'tikka', 'butter_chicken', 'mushroom', 'corn',
    'broccoli', 'carrot', 'cucumber', 'tomato', 'onion', 'potato', 'sweet_potato',
    'mango', 'papaya', 'coconut', 'dates', 'almonds', 'cashews', 'walnuts',
    'milk', 'yogurt', 'cheese', 'butter', 'ghee', 'honey', 'sugar',
    'coffee', 'tea', 'juice', 'smoothie', 'milkshake', 'lassi', 'chai'
]

# Mapping from detected food to nutrition database names
FOOD_TO_DATABASE = {
    'pizza': 'Pizza',
    'burger': 'Burger',
    'egg': 'Eggs',
    'omelette': 'Eggs',
    'banana': 'Banana',
    'rice': 'Rice (White)',
    'bread': 'Roti',
    'naan': 'Roti',
    'chapati': 'Roti',
    'roti': 'Roti',
    'paneer': 'Paneer',
    'dal': 'Dal (Lentils)',
    'lentils': 'Dal (Lentils)',
    'chicken_breast': 'Chicken Breast',
    'steak': 'Chicken Breast',
    'drumstick': 'Chicken Breast',
    'avocado': 'Avocado',
    'salad': 'Avocado',  # fallback
    'soup': 'Dal (Lentils)',  # fallback
}

# Confidence threshold
CONFIDENCE_THRESHOLD = 0.3

def load_model():
    """Load the pre-trained MobileNetV2 model"""
    model = MobileNetV2(weights='imagenet')
    return model

def predict_food(image_path, model=None):
    """
    Predict food from an image file
    Returns: dict with predicted food and confidence, or error message
    """
    try:
        if not os.path.exists(image_path):
            return {"error": "Image file not found"}

        if model is None:
            model = load_model()

        # Load and preprocess image
        img = image.load_img(image_path, target_size=(224, 224))
        img_array = image.img_to_array(img)
        img_array = np.expand_dims(img_array, axis=0)
        img_array = preprocess_input(img_array)

        # Predict
        predictions = model.predict(img_array, verbose=0)
        decoded = decode_predictions(predictions, top=5)[0]

        # Look for food-related predictions
        results = []
        for pred in decoded:
            class_id, class_name, confidence = pred
            # Check if the predicted class is food-related
            if any(food_class in class_name.lower().replace('_', ' ') for food_class in FOOD_CLASSES):
                results.append({
                    "label": class_name,
                    "confidence": float(confidence),
                    "food_name": FOOD_TO_DATABASE.get(class_name, class_name.replace('_', ' ').title())
                })

        if results:
            best = max(results, key=lambda x: x['confidence'])
            if best['confidence'] >= CONFIDENCE_THRESHOLD:
                return {
                    "success": True,
                    "predictions": results,
                    "best_match": best['food_name'],
                    "confidence": best['confidence']
                }

        return {
            "success": False,
            "error": "Food image unclear. Please enter manually.",
            "predictions": results
        }

    except Exception as e:
        return {"error": f"Prediction error: {str(e)}"}


def predict_food_from_bytes(image_bytes, model=None):
    """Predict food from raw image bytes"""
    import tempfile
    with tempfile.NamedTemporaryFile(delete=False, suffix='.jpg') as tmp:
        tmp.write(image_bytes)
        tmp_path = tmp.name

    try:
        result = predict_food(tmp_path, model)
        return result
    finally:
        os.unlink(tmp_path)


if __name__ == "__main__":
    # Test mode
    if len(sys.argv) > 1:
        result = predict_food(sys.argv[1])
        print(json.dumps(result, indent=2))
    else:
        print("CNN Food Detection Model Loaded")
        print("Usage: python cnn_food_detection.py <image_path>")