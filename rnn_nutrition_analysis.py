"""
RNN - Nutrition Pattern Analysis
Tracks repeated eating patterns, skipped meals, protein deficiency, high sugar intake
and provides smart recommendations
"""

import numpy as np
import json
import sys
from collections import Counter, defaultdict
from datetime import datetime, timedelta


class NutritionPatternAnalyzer:
    """
    Analyzes user's nutrition patterns using RNN-style sequence analysis
    (implemented with state tracking and pattern recognition)
    """

    def __init__(self):
        self.nutrition_history = []
        self.patterns = {
            'protein_deficiency_days': 0,
            'high_sugar_days': 0,
            'skipped_meals_count': 0,
            'repeated_patterns': [],
            'current_streak': 0
        }

    def analyze_meal_history(self, meals_data, days=7):
        """
        Analyze meal history data and detect patterns
        
        meals_data: list of dicts with keys - food_name, quantity, meal_time, calories, protein, carbs, fat, fiber, sugar
        """
        if not meals_data:
            return {
                'patterns': [],
                'recommendations': ['Start tracking your meals to get personalized insights!'],
                'deficiencies': [],
                'excesses': [],
                'score': 50
            }

        df = meals_data
        analysis = {
            'patterns': [],
            'recommendations': [],
            'deficiencies': [],
            'excesses': [],
            'score': 100,
            'daily_summary': {}
        }

        # Group meals by date
        meals_by_date = defaultdict(list)
        for meal in df:
            try:
                meal_time = meal.get('meal_time', '')
                if ' ' in str(meal_time):
                    date_part = str(meal_time).split(' ')[0]
                else:
                    date_part = str(meal_time)[:10]
                meals_by_date[date_part].append(meal)
            except:
                continue

        total_days = len(meals_by_date)
        if total_days == 0:
            return analysis

        # Analyze daily nutrition
        for date, meals in meals_by_date.items():
            day_totals = {
                'calories': sum(float(m.get('calories', 0)) * float(m.get('quantity', 1)) for m in meals),
                'protein': sum(float(m.get('protein', 0)) * float(m.get('quantity', 1)) for m in meals),
                'carbs': sum(float(m.get('carbs', 0)) * float(m.get('quantity', 1)) for m in meals),
                'fat': sum(float(m.get('fat', 0)) * float(m.get('quantity', 1)) for m in meals),
                'fiber': sum(float(m.get('fiber', 0)) * float(m.get('quantity', 1)) for m in meals),
                'sugar': sum(float(m.get('sugar', 0)) * float(m.get('quantity', 1)) for m in meals),
                'meal_count': len(meals)
            }
            analysis['daily_summary'][date] = day_totals

            # Check protein deficiency
            if day_totals['protein'] < 40:
                analysis['deficiencies'].append(f"Low protein on {date}: {day_totals['protein']:.1f}g")
                self.patterns['protein_deficiency_days'] += 1

            # Check high sugar
            if day_totals['sugar'] > 50:
                analysis['excesses'].append(f"High sugar on {date}: {day_totals['sugar']:.1f}g")
                self.patterns['high_sugar_days'] += 1

            # Check skipped meals (less than 3 meals)
            if day_totals['meal_count'] < 2:
                self.patterns['skipped_meals_count'] += 1

        # Detect repeated eating patterns
        food_counter = Counter()
        for meal in df:
            food_counter[meal.get('food_name', '')] += 1

        repeated_foods = [food for food, count in food_counter.most_common(5) if count >= 3]
        if repeated_foods:
            self.patterns['repeated_patterns'] = repeated_foods
            analysis['patterns'].append(f"Repeated foods detected: {', '.join(repeated_foods)}")

        # Generate recommendations based on detected patterns
        if self.patterns['protein_deficiency_days'] >= 3:
            analysis['recommendations'].append(
                f"Protein intake low for {self.patterns['protein_deficiency_days']} days. "
                "Increase paneer, eggs, chicken, or lentils in your diet."
            )
            analysis['score'] -= 15

        if self.patterns['high_sugar_days'] >= 3:
            analysis['recommendations'].append(
                f"High sugar intake detected for {self.patterns['high_sugar_days']} days. "
                "Reduce sugary foods and drinks."
            )
            analysis['score'] -= 15

        if self.patterns['skipped_meals_count'] >= 3:
            analysis['recommendations'].append(
                f"You've skipped meals on {self.patterns['skipped_meals_count']} days. "
                "Try to eat regular meals to maintain energy levels."
            )
            analysis['score'] -= 10

        # Analyze meal timing patterns (RNN-like sequence analysis)
        meal_times = []
        for meal in df:
            try:
                mt = meal.get('meal_time', '')
                if ' ' in str(mt):
                    time_part = str(mt).split(' ')[1]
                else:
                    time_part = str(mt)
                hour = int(time_part.split(':')[0])
                meal_times.append(hour)
            except:
                continue

        if meal_times:
            avg_meal_hour = np.mean(meal_times)
            if avg_meal_hour > 21:
                analysis['patterns'].append("Late-night eating pattern detected")
                analysis['recommendations'].append(
                    "Consider eating dinner earlier for better digestion and sleep."
                )
                analysis['score'] -= 10

            # Check for consistent meal times (good habit)
            if len(meal_times) >= 5:
                time_std = np.std(meal_times)
                if time_std < 2:
                    analysis['patterns'].append("Consistent meal timing - great habit!")
                    analysis['score'] += 10

        # Add general recommendations
        if analysis['score'] >= 80:
            analysis['recommendations'].append("Great nutrition habits! Keep it up!")
        elif analysis['score'] >= 60:
            analysis['recommendations'].append("Good progress! Small improvements can make a big difference.")

        # Nutritional balance check
        if total_days >= 3:
            avg_fiber = np.mean([s['fiber'] for s in analysis['daily_summary'].values()])
            if avg_fiber < 20:
                analysis['recommendations'].append(
                    f"Average fiber intake is {avg_fiber:.1f}g. Aim for 25-30g daily. "
                    "Add more vegetables, fruits, and whole grains."
                )

        return analysis

    def get_weekly_trend(self, meals_data):
        """Get weekly nutrition trend analysis"""
        analysis = self.analyze_meal_history(meals_data, days=7)

        # Calculate week-over-week changes if enough data
        if len(analysis.get('daily_summary', {})) >= 2:
            # Simple trend detection
            daily_calories = [s['calories'] for s in analysis['daily_summary'].values()]
            if len(daily_calories) >= 2:
                trend = "increasing" if daily_calories[-1] > daily_calories[0] else "decreasing" if daily_calories[-1] < daily_calories[0] else "stable"
                analysis['calorie_trend'] = trend

        return analysis


def analyze_nutrition_patterns(meals_data_json):
    """Main entry point - called from PHP"""
    meals_data = json.loads(meals_data_json) if meals_data_json else []
    analyzer = NutritionPatternAnalyzer()
    result = analyzer.analyze_meal_history(meals_data)
    return json.dumps(result)


if __name__ == "__main__":
    if len(sys.argv) > 1:
        # Called from PHP
        result = analyze_nutrition_patterns(sys.argv[1])
        print(result)
    else:
        # Demo mode
        sample_data = [
            {"food_name": "Pizza", "quantity": 1, "meal_time": "2024-01-01 22:30:00", "calories": 590, "protein": 25, "carbs": 45, "fat": 30, "fiber": 3, "sugar": 10},
            {"food_name": "Burger", "quantity": 1, "meal_time": "2024-01-01 23:00:00", "calories": 590, "protein": 25, "carbs": 45, "fat": 30, "fiber": 3, "sugar": 10},
            {"food_name": "Pizza", "quantity": 1, "meal_time": "2024-01-02 22:00:00", "calories": 590, "protein": 25, "carbs": 45, "fat": 30, "fiber": 3, "sugar": 10},
            {"food_name": "Roti", "quantity": 2, "meal_time": "2024-01-03 13:00:00", "calories": 120, "protein": 4, "carbs": 22, "fat": 1.5, "fiber": 2.5, "sugar": 0.5},
            {"food_name": "Dal (Lentils)", "quantity": 1, "meal_time": "2024-01-03 13:00:00", "calories": 116, "protein": 7.9, "carbs": 20, "fat": 0.4, "fiber": 7.9, "sugar": 0.6},
            {"food_name": "Paneer", "quantity": 1, "meal_time": "2024-01-04 14:00:00", "calories": 290, "protein": 18, "carbs": 3.6, "fat": 22, "fiber": 1.4, "sugar": 1.2},
        ]
        result = analyze_nutrition_patterns(json.dumps(sample_data))
        print(result)