"""
NLP + ML Chatbot - Nutrition Assistant
Comprehensive nutrition knowledge base with smart question answering
"""

import json
import sys
import re
import numpy as np

# Comprehensive nutrition knowledge base
NUTRITION_KB = {
    'general_nutrition': {
        'keywords': ['nutrition', 'healthy eating', 'diet', 'balanced diet', 'food guide', 'eat healthy'],
        'responses': [
            "A balanced diet includes fruits, vegetables, whole grains, protein, and healthy fats. Aim for variety and moderation!",
            "The key to healthy eating is balance and consistency. Include foods from all food groups in appropriate portions.",
            "According to UCSF Health, a healthy diet emphasizes fruits, vegetables, whole grains, and fat-free or low-fat dairy products."
        ]
    },
    'calories': {
        'keywords': ['calories', 'calorie', 'kcal', 'energy'],
        'responses': [
            "For weight loss, aim for 1500-1800 kcal/day for women and 2000-2500 kcal/day for men, depending on activity level.",
            "A calorie deficit of 300-500 kcal/day leads to healthy weight loss of 0.5 kg per week.",
            "Don't go below 1200 kcal/day without medical supervision. Extreme restriction can slow metabolism.",
            "Your calorie needs depend on age, weight, height, gender, and activity level."
        ]
    },
    'protein': {
        'keywords': ['protein', 'proteins', 'amino acid'],
        'responses': [
            "Protein is essential for muscle building and repair. Aim for 1.2-2g per kg of body weight!",
            "Good vegetarian protein sources: Paneer (18g/100g), Dal (8g/100g), Tofu (8g/100g), Greek yogurt (10g/100g), Sprouts.",
            "For muscle gain, consume protein within 30 minutes post-workout. Spread intake across 4-5 meals.",
            "Plant proteins: lentils, chickpeas, tofu, quinoa, nuts, and seeds are excellent choices for vegetarians."
        ]
    },
    'carbs': {
        'keywords': ['carbs', 'carbohydrates', 'carb'],
        'responses': [
            "Carbs are your body's main energy source. Choose complex carbs like whole grains over refined carbs.",
            "Good carbs: Brown rice, whole wheat roti, oats, quinoa, vegetables, fruits.",
            "Simple carbs (sugar, white bread, white rice) should be limited. Complex carbs (whole grains, legumes) are healthier.",
            "For fat loss, reduce refined carbs but don't eliminate them completely - your brain needs glucose!"
        ]
    },
    'fats': {
        'keywords': ['fat', 'fats', 'oil', 'ghee', 'butter', 'healthy fat', 'omega'],
        'responses': [
            "Healthy fats are crucial for hormone function and nutrient absorption. Include nuts, avocados, olive oil.",
            "Omega-3 fatty acids reduce inflammation. Sources: walnuts, flaxseeds, chia seeds, soybeans.",
            "Ghee and coconut oil are fine in moderation - about 1-2 tablespoons daily. They contain healthy MCTs.",
            "Avoid trans fats and limit saturated fats. Choose unsaturated fats from plant sources."
        ]
    },
    'weight_loss': {
        'keywords': ['weight loss', 'lose weight', 'fat loss', 'slim', 'reduce weight', 'dieting'],
        'responses': [
            "For fat loss: maintain a calorie deficit of 300-500 kcal/day, eat protein-rich foods, and exercise regularly.",
            "According to Max Healthcare, the best approach combines strength training with cardio for optimal fat loss.",
            "Focus on whole foods, drink 2-3L water daily, get 7-8 hours of sleep, and manage stress for best results.",
            "Crash diets don't work long-term. Aim for 0.5-1 kg weight loss per week for sustainable results."
        ]
    },
    'weight_gain': {
        'keywords': ['weight gain', 'gain weight', 'bulk', 'mass gain', 'underweight'],
        'responses': [
            "For healthy weight gain, eat in a calorie surplus of 300-500 kcal above maintenance.",
            "Focus on nutrient-dense foods: nuts, dried fruits, whole milk, paneer, avocados, bananas, and whole grains.",
            "Eat 5-6 smaller meals throughout the day rather than 3 large ones to increase total intake.",
            "Strength training is key - combine with adequate protein intake (1.6-2g per kg body weight)."
        ]
    },
    'muscle_gain': {
        'keywords': ['muscle gain', 'build muscle', 'muscle building', 'strength'],
        'responses': [
            "Best breakfast for muscle gain: Paneer paratha with curd, or oats with milk, nuts, and banana.",
            "Eat in a calorie surplus (300-500 kcal above maintenance) for optimal muscle gain.",
            "Protein timing: Spread intake across 4-5 meals for optimal muscle protein synthesis.",
            "Don't forget carbs! They fuel your workouts and aid recovery. Eat within 2 hours post-workout."
        ]
    },
    'fiber': {
        'keywords': ['fiber', 'fibre', 'digestion', 'constipation'],
        'responses': [
            "Fiber is crucial for digestive health. Aim for 25-30g daily for adults.",
            "Good fiber sources: oats, dal, whole grains, vegetables, fruits, nuts, and seeds.",
            "Increase fiber gradually and drink plenty of water to avoid digestive discomfort.",
            "Gujarati diet is naturally high in fiber with dishes like thepla, handvo, and vegetable sabzis."
        ]
    },
    'vitamins': {
        'keywords': ['vitamin', 'vitamins', 'minerals', 'micronutrient'],
        'responses': [
            "Vitamin B12 is important for vegetarians - sources include dairy products, fortified foods, and supplements.",
            "Iron deficiency is common. Boost absorption by combining iron-rich foods with Vitamin C (lemon, amla).",
            "Vitamin D from sunlight 15-20 minutes daily. For deficiency, consult your doctor about supplements.",
            "Include a variety of colorful vegetables and fruits to get a wide range of vitamins and minerals."
        ]
    },
    'water': {
        'keywords': ['water', 'hydrate', 'hydration', 'drink', 'thirsty', 'dehydration'],
        'responses': [
            "Drink 2-3 liters (8-12 glasses) of water daily. More if you exercise or live in a hot climate.",
            "According to UCSF Health, drinking water before meals can help reduce calorie intake by increasing fullness.",
            "Signs of dehydration: dark urine, headache, fatigue, dry mouth. Drink water throughout the day!",
            "Coconut water, buttermilk (chaas), herbal teas, and lemon water also contribute to hydration."
        ]
    },
    'exercise': {
        'keywords': ['exercise', 'workout', 'gym', 'cardio', 'run', 'walk', 'fitness'],
        'responses': [
            "What workout burns calories? Running burns ~10 kcal/min, brisk walking ~5 kcal/min, cycling ~8 kcal/min.",
            "Combine strength training (3x/week) with cardio (3x/week) for optimal health and body composition.",
            "Morning workouts on an empty stomach may burn more fat, but eating protein afterward is crucial for recovery.",
            "Rest days are important for muscle recovery and growth. Aim for at least 1-2 rest days per week."
        ]
    },
    'sleep': {
        'keywords': ['sleep', 'insomnia', 'rest', 'bedtime'],
        'responses': [
            "Poor sleep can disrupt hunger hormones and increase cravings for unhealthy foods.",
            "Aim for 7-9 hours of quality sleep per night. Avoid screens 1 hour before bedtime.",
            "Eating heavy meals close to bedtime can disrupt sleep quality. Try to finish dinner 2-3 hours before bed.",
            "According to Max Healthcare, good sleep hygiene includes a consistent sleep schedule and relaxing bedtime routine."
        ]
    },
    'specific_foods': {
        'pizza': {
            'keywords': ['pizza'],
            'responses': [
                "Pizza is high in calories and carbs. For fat loss, enjoy it occasionally - balance is key!",
                "One slice of pizza has about 266 kcal, 11g protein, 33g carbs, 10g fat. Choose thin crust with veggie toppings.",
                "Want pizza but watching calories? Try a whole wheat base with low-fat cheese and lots of vegetables."
            ]
        },
        'roti': {
            'keywords': ['roti', 'chapati', 'phulka'],
            'responses': [
                "One medium whole wheat roti: 120 kcal, 4g protein, 22g carbs, 1.5g fat. A healthy staple!",
                "Whole wheat roti is better than naan or paratha for weight management. It's rich in fiber.",
                "Ragi (nachni) roti is even healthier - rich in calcium and iron, great for bones and blood."
            ]
        },
        'paneer': {
            'keywords': ['paneer', 'cottage cheese'],
            'responses': [
                "Paneer has 18g protein and 290 kcal per 100g. Excellent for muscle gain and satiety!",
                "Low-fat paneer has fewer calories while maintaining high protein content. Great for weight loss diets.",
                "Combine paneer with vegetables (palak paneer, matar paneer) for a balanced, protein-rich meal."
            ]
        },
        'dal': {
            'keywords': ['dal', 'lentils', 'dal tadka'],
            'responses': [
                "Dal (lentils) provide 8g protein and 7g fiber per 100g. A nutritional powerhouse for vegetarians!",
                "Different dals offer different benefits: Moong dal is easiest to digest, Toor dal is rich in folate, Masoor dal is high in iron.",
                "Combining dal with rice or roti creates a complete protein with all essential amino acids."
            ]
        },
        'rice': {
            'keywords': ['rice', 'chawal', 'biryani', 'pulao'],
            'responses': [
                "100g cooked white rice: 130 kcal. For weight loss, limit to 1 small bowl per meal.",
                "Brown rice is more nutritious than white rice - more fiber, B vitamins, and minerals.",
                "For diabetics, brown rice or parboiled rice is better than white rice as it has a lower glycemic index."
            ]
        },
        'banana': {
            'keywords': ['banana', 'kela'],
            'responses': [
                "Banana: 90 kcal, rich in potassium and Vitamin B6. Great pre-workout snack!",
                "One banana provides quick energy for workouts. Pair with nuts for sustained energy.",
                "Green (raw) bananas are rich in resistant starch which is great for gut health."
            ]
        },
        'eggs': {
            'keywords': ['egg', 'eggs', 'omelette'],
            'responses': [
                "Eggs are a complete protein! One egg: 70 kcal, 6g protein. However, they're not suitable for pure vegetarians.",
                "For vegetarians, tofu scramble is an excellent egg alternative with similar protein content.",
                "Egg whites are pure protein, yolks contain healthy fats and vitamins A, D, E, B12."
            ]
        },
        'dhokla': {
            'keywords': ['dhokla', 'khaman'],
            'responses': [
                "Dhokla is a healthy Gujarati steamed snack! 160 kcal per serving, 6g protein, low in fat.",
                "Being steamed (not fried), dhokla is one of the healthiest Indian snack options. High in fiber too!",
                "Dhokla is made from fermented batter which is good for gut health due to probiotics."
            ]
        },
        'thepla': {
            'keywords': ['thepla', 'methi thepla'],
            'responses': [
                "Methi Thepla: 135 kcal each, rich in iron and fiber from fenugreek leaves. A healthy Gujarati flatbread!",
                "Theplas are great for travel and have a longer shelf life. The fenugreek helps control blood sugar.",
                "Made with whole wheat and methi, theplas are rich in Vitamin K, iron, and dietary fiber."
            ]
        },
        'jalebi': {
            'keywords': ['jalebi', 'jalebi'],
            'responses': [
                "Jalebi: 280 kcal per serving, high in sugar (40g). Best enjoyed occasionally as a treat!",
                "Deep-fried and sugar-soaked, jalebi should be limited. 1-2 pieces occasionally is fine.",
                "Healthier sweet alternatives: fruit-based desserts, dates, or baked sweets instead of fried ones."
            ]
        },
        'ghee': {
            'keywords': ['ghee', 'clarified butter'],
            'responses': [
                "Ghee is rich in healthy fats and fat-soluble vitamins A, D, E, K. 1 tbsp has 120 kcal.",
                "According to Ayurveda and Max Healthcare, ghee in moderation (1-2 tsp/day) aids digestion and nutrient absorption.",
                "Ghee has a high smoke point, making it suitable for cooking. It contains butyrate which supports gut health."
            ]
        }
    },
    'health_conditions': {
        'diabetes': {
            'keywords': ['diabetes', 'diabetic', 'blood sugar', 'sugar level'],
            'responses': [
                "For diabetes management: eat regular meals, choose low GI foods, avoid sugary drinks, and monitor portions.",
                "Good foods for diabetes: whole grains, dal, vegetables, nuts, seeds, and lean proteins.",
                "Karela (bitter gourd), methi (fenugreek), and jamun are known to help manage blood sugar levels naturally."
            ]
        },
        'heart_health': {
            'keywords': ['heart', 'cholesterol', 'blood pressure', 'cardiac'],
            'responses': [
                "For heart health: reduce saturated fats, increase fiber, limit sodium (salt), and eat omega-3 rich foods.",
                "Heart-healthy foods: oats, nuts, olive oil, avocados, berries, and green leafy vegetables.",
                "According to UCSF Health, limiting sodium to 1500-2300mg daily helps maintain healthy blood pressure."
            ]
        },
        'digestion': {
            'keywords': ['digestion', 'gas', 'bloating', 'acidity', 'stomach'],
            'responses': [
                "For good digestion: eat fiber-rich foods, stay hydrated, eat slowly, and avoid overeating.",
                "Probiotic foods like curd/yogurt, buttermilk, and fermented foods (idli, dhokla) aid digestion.",
                "Ginger, cumin (jeera), fennel (saunf), and peppermint can help relieve bloating and indigestion."
            ]
        }
    }
}


def preprocess_text(text):
    """Clean and normalize text"""
    text = text.lower().strip()
    text = re.sub(r'[^\w\s]', '', text)
    return text


def get_best_response(user_message):
    """Find the best response using NLP keyword matching"""
    processed = preprocess_text(user_message)
    if not processed:
        return "I didn't understand that. Could you please rephrase your question about nutrition, diet, or fitness?"

    # Check specific foods first
    for food, data in NUTRITION_KB.get('specific_foods', {}).items():
        for keyword in data['keywords']:
            if keyword in processed:
                response = np.random.choice(data['responses'])
                return _add_food_nutrition_info(food, response)

    # Check health conditions
    for condition, data in NUTRITION_KB.get('health_conditions', {}).items():
        for keyword in data['keywords']:
            if keyword in processed:
                return np.random.choice(data['responses'])

    # Check main categories
    scores = {}
    for category, data in NUTRITION_KB.items():
        if category in ['specific_foods', 'health_conditions']:
            continue
        score = 0
        for keyword in data['keywords']:
            if keyword in processed:
                score += 1
        if score > 0:
            scores[category] = score

    if scores:
        best_category = max(scores, key=scores.get)
        responses = NUTRITION_KB[best_category]['responses']
        return np.random.choice(responses)

    return _get_fallback_response(processed)


def _add_food_nutrition_info(food_name, response):
    """Append nutrition info when the user asks about specific foods"""
    food_nutrition = {
        'pizza': '🍕 Pizza (1 slice): 266 kcal, 11g protein, 33g carbs, 10g fat',
        'roti': '🫓 Roti (1 medium): 120 kcal, 4g protein, 22g carbs, 1.5g fat',
        'paneer': '🧀 Paneer (100g): 290 kcal, 18g protein, 3.6g carbs, 22g fat',
        'dal': '🥣 Dal (100g cooked): 116 kcal, 8g protein, 20g carbs, 0.4g fat, 8g fiber',
        'rice': '🍚 Rice (100g cooked): 130 kcal, 2.7g protein, 28g carbs, 0.3g fat',
        'dhokla': '🫓 Dhokla (1 piece): 160 kcal, 6g protein, 25g carbs, 4g fat',
        'thepla': '🫓 Thepla (1 piece): 135 kcal, 4.5g protein, 22g carbs, 4g fat',
        'ghee': '🥄 Ghee (1 tbsp): 120 kcal, 0g protein, 0g carbs, 14g fat',
        'jalebi': '🍩 Jalebi (100g): 280 kcal, 2g protein, 55g carbs, 6g fat, 40g sugar',
        'eggs': '🥚 Egg (1 whole): 70 kcal, 6g protein, 1g carbs, 5g fat',
        'banana': '🍌 Banana (1 medium): 90 kcal, 1.1g protein, 23g carbs, 0.3g fat',
        'apple': '🍎 Apple (1 medium): 52 kcal, 0.3g protein, 14g carbs, 0.2g fat',
        'mango': '🥭 Mango (100g): 60 kcal, 0.8g protein, 15g carbs, 0.4g fat',
        'idli': '🫓 Idli (2): 150 kcal, 5g protein, 30g carbs, 1g fat',
        'dosa': '🫓 Dosa (1): 170 kcal, 4g protein, 30g carbs, 4g fat',
        'samosa': '🥟 Samosa (1): 210 kcal, 4g protein, 25g carbs, 11g fat',
        'pav bhaji': '🍛 Pav Bhaji (1 plate): 350 kcal, 8g protein, 45g carbs, 15g fat',
        'milk': '🥛 Milk (100ml): 65 kcal, 3.3g protein, 4.8g carbs, 3.5g fat',
        'curd': '🥄 Curd/Yogurt (100g): 60 kcal, 4g protein, 5g carbs, 3g fat',
        'khichdi': '🍚 Khichdi (1 plate): 180 kcal, 6g protein, 30g carbs, 4g fat'
    }

    for key, info in food_nutrition.items():
        if key in food_name.lower():
            return info + "\n\n" + response

    return response


def _get_fallback_response(text):
    """Generate contextual fallback"""
    fallbacks = [
        "I'm not sure about that specifically. Ask me about calories, protein, specific foods like paneer, roti, or dhokla, or health tips!",
        "Try asking: 'How many calories in roti?', 'Best breakfast for muscle gain?', 'What foods help with weight loss?' or 'Is ghee healthy?'",
        f"I can help with nutrition questions about foods, diet plans, weight management, and healthy eating. Try asking something specific!"
    ]
    return np.random.choice(fallbacks)


# Specific question patterns
QUESTION_PATTERNS = [
    (r'how many calories in (\w+(?:\s*\w+)?)', 'calories_in_food'),
    (r'(?:can|should) i eat (\w+(?:\s*\w+)?)', 'can_i_eat'),
    (r'best (?:breakfast|lunch|dinner|food) for (\w+(?:\s*\w+)?)', 'best_meal_for'),
    (r'what (?:workout|exercise) burns (\d+)', 'workout_burns'),
    (r'how much (?:protein|carbs|fat|fiber) in (\w+(?:\s*\w+)?)', 'nutrient_in_food'),
    (r'(?:what|which) food[s]? (?:is|are) (?:good|best|healthy) for (\w+(?:\s*\w+)?)', 'food_for_condition'),
    (r'what is (?:the )?(?:calorie|protein|fat|carbs) content of (\w+(?:\s*\w+)?)', 'food_content'),
]


def handle_specific_patterns(user_message):
    """Handle specific question patterns"""
    processed = preprocess_text(user_message)

    for pattern, intent in QUESTION_PATTERNS:
        match = re.search(pattern, processed)
        if match:
            if intent in ['workout_burns']:
                calories = match.group(1)
                return f"To burn {calories} kcal, you could:\n• 🏃 Run: {int(int(calories)/10)} minutes\n• 🚶 Walk briskly: {int(int(calories)/5)} minutes\n• 🚴 Cycle: {int(int(calories)/8)} minutes\n• 🏊 Swim: {int(int(calories)/9)} minutes"
            elif intent in ['food_for_condition']:
                condition = match.group(1)
                if condition in ['diabetes', 'diabetic', 'blood sugar']:
                    return "For diabetes: Bitter gourd (karela), fenugreek (methi), jamun, whole grains, dal, green leafy vegetables, and nuts are excellent choices."
                elif condition in ['heart', 'cholesterol']:
                    return "For heart health: Oats, nuts (especially almonds and walnuts), olive oil, avocados, berries, green tea, and leafy greens are excellent."
                elif condition in ['digestion', 'gas', 'bloating']:
                    return "For digestion: Yogurt, buttermilk, ginger, fennel seeds (saunf), papaya, and high-fiber foods like oats and dal are great."
                elif condition in ['weight loss', 'fat loss']:
                    return "For weight loss: Leafy greens, high-protein foods (paneer, dal), oats, green tea, soups, and fruits like apple and watermelon."
                else:
                    return get_best_response(condition)
            elif intent in ['nutrient_in_food', 'food_content', 'calories_in_food']:
                food = match.group(1)
                return _add_food_nutrition_info(food, get_best_response(food))

    return None


def chatbot_response(user_message):
    """Main entry point for chatbot"""
    # Check specific patterns first
    pattern_response = handle_specific_patterns(user_message)
    if pattern_response:
        return pattern_response

    # Get general response
    return get_best_response(user_message)


def get_chatbot_response_from_php(message):
    """Called from PHP"""
    response = chatbot_response(message)
    return json.dumps({
        'message': message,
        'response': response,
        'success': True
    })


if __name__ == "__main__":
    if len(sys.argv) > 1:
        message = sys.argv[1]
        result = get_chatbot_response_from_php(message)
        print(result)
    else:
        # Interactive mode
        print("🤖 Nutrition Assistant (type 'quit' to exit)")
        print("Ask me anything about nutrition, foods, diets, etc!")
        while True:
            msg = input("\nYou: ")
            if msg.lower() == 'quit':
                break
            response = chatbot_response(msg)
            print(f"\nBot: {response}")