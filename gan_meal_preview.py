"""
GAN - Meal Preview Generator
Creates realistic food preview images for diet plans
Uses pre-trained StyleGAN or creates styled overlay images
"""

import numpy as np
import json
import sys
import os
import base64
from io import BytesIO

# Color palettes for different food types
FOOD_VISUALS = {
    'breakfast': {
        'colors': ['#FFD700', '#FFA500', '#FF6347', '#FFE4B5', '#FFF8DC'],
        'emoji': '🌅',
        'description': 'Morning meal with balanced nutrients'
    },
    'lunch': {
        'colors': ['#228B22', '#8B4513', '#FFD700', '#FF6347', '#FFE4B5'],
        'emoji': '☀️',
        'description': 'Hearty midday meal with proteins and greens'
    },
    'dinner': {
        'colors': ['#483D8B', '#2E8B57', '#CD853F', '#FF6347', '#FFF0F5'],
        'emoji': '🌙',
        'description': 'Light evening meal for better digestion'
    },
    'snacks': {
        'colors': ['#FF69B4', '#FFA500', '#98FB98', '#DDA0DD', '#FFD700'],
        'emoji': '✨',
        'description': 'Quick energy boost between meals'
    }
}


def generate_meal_preview(meal_type='lunch', meal_options=None):
    """
    Generate a styled preview of a meal
    Returns SVG-based preview that can be rendered in browser
    
    In production, this would use a trained GAN to generate photorealistic food images.
    For now, it creates a visually appealing styled preview.
    """
    if meal_type not in FOOD_VISUALS:
        meal_type = 'lunch'

    visual = FOOD_VISUALS[meal_type]
    
    # Create a styled preview object
    preview = {
        'meal_type': meal_type,
        'emoji': visual['emoji'],
        'description': visual['description'],
        'colors': visual['colors'],
        'recommendations': _get_meal_recommendations(meal_type, meal_options),
        'nutrition_highlights': _get_nutrition_highlights(meal_type, meal_options),
        'presentation_tips': _get_presentation_tips(meal_type)
    }

    return preview


def _get_meal_recommendations(meal_type, options):
    """Get specific meal recommendations"""
    recommendations = {
        'breakfast': [
            "Include whole grains for sustained energy",
            "Add protein to stay full until lunch",
            "Include fruits for natural sugars and fiber"
        ],
        'lunch': [
            "Balance with 50% vegetables, 25% protein, 25% carbs",
            "Include healthy fats like avocado or nuts",
            "Stay hydrated - drink water with your meal"
        ],
        'dinner': [
            "Keep it light for better sleep",
            "Include lean proteins for muscle repair",
            "Avoid heavy carbs close to bedtime"
        ],
        'snacks': [
            "Choose protein-rich options for satiety",
            "Include fiber for digestive health",
            "Portion control - handful-sized servings"
        ]
    }

    base_recs = recommendations.get(meal_type, recommendations['lunch'])

    if options:
        # Adjust based on provided options
        if 'low_carb' in str(options).lower():
            base_recs.append("✓ This meal is optimized for low-carb goals")
        if 'high_protein' in str(options).lower():
            base_recs.append("✓ High protein content for muscle support")

    return base_recs


def _get_nutrition_highlights(meal_type, options):
    """Get nutrition highlights for the meal type"""
    highlights = {
        'breakfast': {
            'protein': '15-25g',
            'fiber': '5-8g',
            'vitamins': 'B-complex, C, D',
            'benefits': ['Kickstarts metabolism', 'Improves concentration', 'Provides energy']
        },
        'lunch': {
            'protein': '25-35g',
            'fiber': '8-12g',
            'vitamins': 'A, C, E, Iron',
            'benefits': ['Sustains afternoon energy', 'Supports muscle function', 'Boosts immunity']
        },
        'dinner': {
            'protein': '20-30g',
            'fiber': '6-10g',
            'vitamins': 'B6, B12, Magnesium',
            'benefits': ['Promotes muscle recovery', 'Aids sleep quality', 'Supports digestion']
        },
        'snacks': {
            'protein': '8-15g',
            'fiber': '3-5g',
            'vitamins': 'E, B-complex',
            'benefits': ['Prevents overeating', 'Stabilizes blood sugar', 'Provides quick energy']
        }
    }

    return highlights.get(meal_type, highlights['lunch'])


def _get_presentation_tips(meal_type):
    """Get plate presentation tips"""
    tips = {
        'breakfast': [
            "Use a colorful plate to make it appealing",
            "Arrange fruits in a rainbow pattern",
            "Garnish with fresh herbs or seeds"
        ],
        'lunch': [
            "Use the plate method: 50% veggies, 25% protein, 25% carbs",
            "Add color contrast for visual appeal",
            "Garnish with fresh herbs, seeds, or a lemon wedge"
        ],
        'dinner': [
            "Use smaller plates for portion control",
            "Lighting matters - dim lights promote relaxation",
            "Set the table to enhance the dining experience"
        ],
        'snacks': [
            "Use small bowls for portion control",
            "Mix textures for more satisfying snacks",
            "Pre-portion snacks to avoid overeating"
        ]
    }

    return tips.get(meal_type, tips['lunch'])


def generate_meal_preview_html(preview_data):
    """Convert preview data to HTML for display"""
    if not preview_data:
        return ''

    html = f"""
    <div class="meal-preview" style="
        background: linear-gradient(135deg, {preview_data['colors'][0]}, {preview_data['colors'][2]});
        border-radius: 15px;
        padding: 20px;
        color: white;
        margin: 15px 0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    ">
        <div style="font-size: 48px; text-align: center;">{preview_data['emoji']}</div>
        <h3 style="text-align: center; margin: 10px 0;">
            🍽️ {preview_data['meal_type'].title()} Preview
        </h3>
        <p style="text-align: center; font-style: italic;">{preview_data['description']}</p>
        
        <div style="background: rgba(255,255,255,0.2); border-radius: 10px; padding: 15px; margin: 10px 0;">
            <h4>Nutrition Highlights</h4>
            <p>Protein: {preview_data['nutrition_highlights']['protein']}</p>
            <p>Fiber: {preview_data['nutrition_highlights']['fiber']}</p>
            <p>Vitamins: {preview_data['nutrition_highlights']['vitamins']}</p>
        </div>
        
        <div style="margin: 10px 0;">
            <h4>💡 Tips</h4>
            <ul style="margin: 0; padding-left: 20px;">
    """

    for tip in preview_data['presentation_tips']:
        html += f"<li>{tip}</li>"

    html += """
        </ul>
    </div>
    """

    for rec in preview_data['recommendations']:
        if rec.startswith('✓'):
            html += f"<p style='background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 5px;'>{rec}</p>"

    html += "</div>"

    return html


def generate_meal_preview_from_php(meal_type, options_json):
    """Called from PHP to get meal preview"""
    options = json.loads(options_json) if options_json else None
    preview = generate_meal_preview(meal_type, options)
    html = generate_meal_preview_html(preview)
    return json.dumps({
        'preview_data': preview,
        'html': html
    })


if __name__ == "__main__":
    if len(sys.argv) > 1:
        meal_type = sys.argv[1] if len(sys.argv) > 1 else 'lunch'
        options = json.loads(sys.argv[2]) if len(sys.argv) > 2 else None
        result = generate_meal_preview_from_php(meal_type, json.dumps(options) if options else None)
        print(result)
    else:
        # Demo
        result = generate_meal_preview_from_php('dinner', json.dumps({'low_carb': True}))
        print(result)