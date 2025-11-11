import sys
import json
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.preprocessing import LabelEncoder
import traceback

def calculate_score(row, user_input):
    score = 0
    
    # Determine product category for strategic weighting
    product_category = str(row.get('Category', '')).lower()
    is_tool_or_universal = (
        'tool' in product_category or 
        'brush' in product_category or
        'applicator' in product_category or
        'brow' in product_category or
        'lash' in product_category or
        # Check if it's truly universal (all attributes are Any/All)
        (str(row.get('skin_type', '')).strip().title() in ['Any', 'All', 'None', 'N/A'] and
         str(row.get('skin_tone', '')).strip().title() in ['Any', 'All', 'None', 'N/A'] and
         str(row.get('undertone', '')).strip().title() in ['Any', 'All', 'None', 'N/A'])
    )
    
    # Skin Type match - REDUCE weight for universal products
    product_skin_type = str(row['skin_type']).strip().title()
    user_skin_type = user_input['Skin_Type'].strip().title()
    
    skin_type_weight = 0.15 if is_tool_or_universal else 0.25
    
    if (product_skin_type == user_skin_type or 
        product_skin_type in ['Any', 'All', 'None', 'N/A'] or
        user_skin_type in ["Don't Care", "Any"]):
        score += skin_type_weight

    # Skin Tone match - REDUCE weight for universal products
    product_skin_tone = str(row['skin_tone']).strip().title()
    user_skin_tone = user_input['Skin_Tone'].strip().title()
    
    skin_tone_weight = 0.15 if is_tool_or_universal else 0.25
    
    skin_tone_match = False
    if (product_skin_tone in ['Any', 'All', 'None', 'N/A'] or
        user_skin_tone in ["Don't Care", "Any"]):
        skin_tone_match = True
    else:
        product_tones = [tone.strip().title() for tone in product_skin_tone.split(',')]
        skin_tone_match = user_skin_tone in product_tones
    
    if skin_tone_match:
        score += skin_tone_weight

    # Undertone match - FIXED
    product_undertone = str(row['undertone']).strip().title()
    user_undertone = user_input['Undertone'].strip().title()
    
    # Normalize user undertone
    undertone_mapping = {
        "Not-Sure": "Don't Know",
        "Not Sure": "Don't Know", 
        "Dont Know": "Don't Know", 
        "Dont-Care": "Don't Care"
    }
    normalized_user_undertone = undertone_mapping.get(user_undertone, user_undertone)

    is_match = False
    if (product_undertone == normalized_user_undertone or 
        product_undertone in ['Any', 'All', ''] or
        normalized_user_undertone in ["Don't Know", "Don't Care", "Not Sure"]):
        is_match = True
    elif product_undertone == 'Neutral' and normalized_user_undertone in ['Cool', 'Warm']:
        is_match = True

    if is_match:
        score += 0.15

    # Skin concerns - KEEP SAME weight (tools don't address concerns)
    user_concerns = [c.strip().title() for c in user_input['Skin_Concerns']]
    
    if not user_concerns or 'None' in user_concerns:
        score += 0.15
    else:
        concern_mapping = {
            'Acne': 'acne',
            'Dryness': 'dryness', 
            'Dark Spots': 'dark_spots',
            'Aging': 'aging'
        }
        
        any_concern_match = False
        for concern in user_concerns:
            col_name = concern_mapping.get(concern, '')
            if col_name and row.get(col_name, 0) == 1:
                any_concern_match = True
                break
        
        if any_concern_match:
            score += 0.15

    # Preference match - REDUCE weight for tools (finish matters less for tools)
    finish_mapping = {
        'Matte': 'matte',
        'Dewy': 'dewy',
        'Long-Lasting': 'long_lasting'
    }
    user_pref = user_input['Preference']
    
    finish_weight = 0.05 if is_tool_or_universal else 0.10
    
    if user_pref in ["Don't Care", "Any"]:
        score += finish_weight
    else:
        user_pref_title = user_pref.title()
        pref_col = finish_mapping.get(user_pref_title, '')
        
        if not pref_col:
            pref_col = finish_mapping.get(user_pref, '')
        
        if pref_col and row.get(pref_col, 0) == 1:
            score += finish_weight

    # PENALTY: If it's a universal product, slightly reduce final score
    # This prevents tools from dominating perfect matches
    final_score = score * 0.9 if is_tool_or_universal else score

    return min(final_score, 1.0)

def get_category_priority(category):
    """Assign priority to categories for better ranking"""
    if pd.isna(category) or not category:
        return 1
    
    category = str(category).lower()
    
    # High priority - skin-interactive products that need precise matching
    if any(word in category for word in ['foundation', 'concealer', 'serum', 'moisturizer', 'treatment', 'cream', 'lotion', 'toner']):
        return 3
    
    # Medium priority - color cosmetics that interact with skin
    elif any(word in category for word in ['blush', 'lipstick', 'eyeshadow', 'makeup', 'primer', 'powder', 'highlighter', 'bronzer']):
        return 2
    
    # Low priority - tools and universal products
    else:
        return 1
    
def score_to_match(p_predicted, p_initial_fit):
    # FIXED: More realistic thresholds
    if p_predicted >= 4.2 and p_initial_fit >= 0.8:
        return "💎 PERFECT MATCH"
    elif p_predicted >= 3.8 and p_initial_fit >= 0.6:
        return "💖 CLOSE MATCH"
    elif p_predicted >= 3.0:
        return "🌸 NORMAL MATCH"
    else:
        return "⚠️ LOW RATING MATCH"

def analyze_attribute_matches(product, user_input):
    matches = {}
    
    # Skin Type match
    product_skin_type = str(product.get('skin_type', 'Any')).strip().title()
    user_skin_type = user_input['Skin_Type'].strip().title()
    
    skin_type_match = (
        product_skin_type == user_skin_type or 
        product_skin_type in ['Any', 'All', 'None', 'N/A'] or
        user_skin_type in ["Don't Care", "Any"]
    )
    
    matches['skin_type'] = {
        'match': skin_type_match,
        'product_value': product_skin_type,
        'user_value': user_skin_type
    }
    
    # Skin Tone match
    product_skin_tone = str(product.get('skin_tone', 'Any')).strip().title()
    user_skin_tone = user_input['Skin_Tone'].strip().title()
    
    skin_tone_match = False
    if (product_skin_tone in ['Any', 'All', 'None', 'N/A'] or
        user_skin_tone in ["Don't Care", "Any"]):
        skin_tone_match = True
    else:
        product_tones = [tone.strip().title() for tone in product_skin_tone.split(',')]
        skin_tone_match = user_skin_tone in product_tones
    
    matches['skin_tone'] = {
        'match': skin_tone_match,
        'product_value': product_skin_tone,
        'user_value': user_skin_tone
    }
    
    # Undertone match - FIXED: Handle "Not-Sure" from frontend
    product_undertone = str(product.get('undertone', 'Any')).strip().title()
    user_undertone = user_input['Undertone'].strip().title()
    
    # Map frontend values to backend values
    undertone_mapping = {
        "Not-Sure": "Don't Know",
        "Not Sure": "Don't Know", 
        "Dont Know": "Don't Know",
        "Dont-Care": "Don't Care"
    }
    
    # Normalize user undertone
    normalized_user_undertone = undertone_mapping.get(user_undertone, user_undertone)
    
    is_undertone_match = False
    if (product_undertone == normalized_user_undertone or 
        product_undertone in ['Any', 'All', 'N/A'] or
        normalized_user_undertone in ["Don't Know", "Don't Care", "Not Sure"]):
        is_undertone_match = True
    elif product_undertone == 'Neutral' and normalized_user_undertone in ['Cool', 'Warm']:
        is_undertone_match = True
        
    matches['undertone'] = {
        'match': is_undertone_match,
        'product_value': product_undertone,
        'user_value': user_undertone  # Keep original for display
    }
    
    # Skin Concerns - FIXED
    user_concerns = [c.strip().title() for c in user_input['Skin_Concerns']]
    concern_mapping = {
        'Acne': 'acne',
        'Dryness': 'dryness', 
        'Dark Spots': 'dark_spots',
        'Aging': 'aging'
    }
    
    concern_matches = {}
    overall_concern_match = False
    
    if not user_concerns or 'None' in user_concerns:
        concern_matches['No Concerns'] = {
            'match': True,
            'product_value': 'Any',
            'user_value': 'None'
        }
        overall_concern_match = True
    else:
        any_concern_matched = False
        for concern in user_concerns:
            col_name = concern_mapping.get(concern, '')
            if col_name:
                product_has_concern = product.get(col_name, 0) == 1
                concern_matches[concern] = {
                    'match': product_has_concern,
                    'product_value': 'Yes' if product_has_concern else 'No',
                    'user_value': 'Needed'
                }
                if product_has_concern:
                    any_concern_matched = True
        
        overall_concern_match = any_concern_matched
    
    matches['concerns'] = concern_matches
    matches['concerns_overall_match'] = overall_concern_match
    
    # Finish preference - FIXED: Handle "Don't Care"
    finish_mapping = {
        'Matte': 'matte',
        'Dewy': 'dewy', 
        'Long-Lasting': 'long_lasting'
    }
    
    product_finishes = []
    for finish_name, db_column in finish_mapping.items():
        if product.get(db_column, 0) == 1:
            product_finishes.append(finish_name)
    
    user_preferred_finish = user_input['Preference']
    
    # If user doesn't care, it's always a match
    if user_preferred_finish in ["Don't Care", "Any"]:
        has_preferred_finish = True
    else:
        user_pref_title = user_preferred_finish.title()
        pref_col = finish_mapping.get(user_pref_title, '')
        
        if not pref_col:
            pref_col = finish_mapping.get(user_preferred_finish, '')
        
        has_preferred_finish = pref_col and product.get(pref_col, 0) == 1
    
    product_finish_text = ', '.join(product_finishes) if product_finishes else 'None'
    
    matches['finish'] = {
        'match': has_preferred_finish,
        'product_value': product_finish_text,
        'user_value': user_preferred_finish
    }
    
    return matches

def create_sample_data():
    """Create realistic sample data for testing"""
    sample_products = [
        {
            'id': 'BLUSH001', 'Name': 'Magic Blusher - Rose', 'Category': 'Blush', 
            'Price': 599.00, 'skin_type': 'Normal', 'skin_tone': 'Fair,Medium,Tan,Deep', 
            'undertone': 'All', 'acne': 0, 'dryness': 0, 'dark_spots': 0,
            'matte': 0, 'dewy': 1, 'long_lasting': 1, 'user_rating': 4.5,
            'productrating': 4.5, 'has_personal_feedback': 0
        },
        {
            'id': 'BLUSH003', 'Name': 'Velvet Blush - Berry', 'Category': 'Blush',
            'Price': 299.00, 'skin_type': 'Normal', 'skin_tone': 'Fair,Medium', 
            'undertone': 'Cool', 'acne': 0, 'dryness': 0, 'dark_spots': 0,
            'matte': 1, 'dewy': 0, 'long_lasting': 1, 'user_rating': 4.2,
            'productrating': 4.2, 'has_personal_feedback': 0
        }
    ]
    
    sample_user_input = {
        'Skin_Type': 'sensitive',
        'Skin_Tone': 'deep', 
        'Undertone': 'cool',
        'Skin_Concerns': ['aging', 'dryness'],
        'Preference': 'matte'
    }
    
    return {
        'user_input': sample_user_input,
        'products': sample_products
    }

def main():
    try:
        # Check if we have a command line argument
        if len(sys.argv) < 2:
            data = create_sample_data()
        else:
            with open(sys.argv[1], 'r') as f:
                data = json.load(f)
        
        user_input = data['user_input']
        products = data['products']
        
        # Convert to DataFrame
        df = pd.DataFrame(products)
        
        if df.empty:
            print(json.dumps([]))
            return
        
        # Prepare data
        df_rf = df.copy()
        
        # Fill missing values
        for col in ['skin_type', 'skin_tone', 'undertone']:
            df_rf[col] = df_rf[col].fillna('Any')
        
        # FIXED: Handle ratings properly - don't default to 4.0 for unrated products
        # Priority: user_rating (from feedback) -> productrating (from Products table) -> moderate default
        if 'user_rating' in df_rf.columns:
            # If user_rating is NULL, use productrating
            df_rf['final_rating'] = df_rf.apply(
                lambda row: row['productrating'] if pd.isna(row['user_rating']) else row['user_rating'],
                axis=1
            )
        else:
            # If no user_rating column at all, use productrating
            df_rf['final_rating'] = df_rf.get('productrating', None)
        
        # For products with no ratings at all, use a moderate 3.5 (not 4.0)
        # This distinguishes unrated products from actually highly-rated ones
        df_rf['final_rating'] = df_rf['final_rating'].fillna(3.5)
        
        # Ensure ratings are within reasonable range
        df_rf['final_rating'] = df_rf['final_rating'].clip(1.0, 5.0)
        
        # Label encoding with proper handling
        label_encoders = {}
        categorical_cols = ['skin_type', 'skin_tone', 'undertone']
        
        for col in categorical_cols:
            # Get all unique values including comma-separated ones
            all_values = set()
            for value in df_rf[col]:
                if ',' in str(value):
                    all_values.update([v.strip().title() for v in str(value).split(',')])
                else:
                    all_values.add(str(value).strip().title())
            
            # Add user input values too
            user_val = user_input[col.replace('_', ' ').title().replace(' ', '_')]
            if user_val:
                all_values.add(user_val.strip().title())
            
            le = LabelEncoder()
            le.fit(list(all_values))
            
            # Encode by taking the first value for comma-separated entries
            df_rf[col + '_enc'] = df_rf[col].apply(
                lambda x: le.transform([str(x).split(',')[0].strip().title()])[0] if pd.notna(x) else 0
            )
            label_encoders[col] = le
        
        # Calculate Initial_Fit_Score
        df_rf['Initial_Fit_Score'] = df_rf.apply(lambda row: calculate_score(row, user_input), axis=1)
        
        # Prepare features - use final_rating which properly handles unrated products
        feature_columns = [
            'skin_type_enc', 'skin_tone_enc', 'undertone_enc', 
            'acne', 'dryness', 'dark_spots', 'matte', 'dewy', 'long_lasting',
            'Initial_Fit_Score', 'final_rating'  # Use the properly handled rating
        ]
        
        # Add additional features if available
        if 'has_personal_feedback' in df_rf.columns:
            feature_columns.append('has_personal_feedback')
        
        # Ensure all features exist
        for feature in feature_columns:
            if feature not in df_rf.columns:
                df_rf[feature] = 0
        
        X = df_rf[feature_columns].fillna(0)
        
        # Use final_rating as target
        y = df_rf['final_rating']
        
        # Train model
        rf = RandomForestRegressor(
            n_estimators=100, 
            random_state=42, 
            min_samples_split=5, 
            min_samples_leaf=2
        )
        rf.fit(X, y)
        
        # Predict
        df_rf['Predicted_Score'] = rf.predict(X)
        
        # Apply match type
        df_rf['Match_Type'] = df_rf.apply(
            lambda row: score_to_match(row['Predicted_Score'], row['Initial_Fit_Score']), 
            axis=1
        )
        
        # Add attribute matches
        df_rf['Attribute_Matches'] = df_rf.apply(
            lambda row: analyze_attribute_matches(row, user_input), 
            axis=1
        )
        
        # Add category priority
        df_rf['Category_Priority'] = df_rf['Category'].apply(get_category_priority)
        
        # ENHANCED SORTING: Properly distinguish between rated and unrated products
        # Penalize unrated products slightly so rated products appear first
        rating_penalty = df_rf.apply(
            lambda row: 0.8 if row['has_personal_feedback'] == 0 and pd.isna(row.get('user_rating', None)) else 1.0,
            axis=1
        )
        
        df_rf['Composite_Score'] = (
            df_rf['final_rating'] * 0.6 +  # 60% weight to ratings
            df_rf['Predicted_Score'] * 0.3 +  # 30% to ML prediction
            df_rf['Initial_Fit_Score'] * 0.1  # 10% to basic fit score
        ) * rating_penalty  # Apply penalty for unrated products
        
        # FIXED: Remove .head(20) to show ALL products, not just top 20
        top_recommendations = df_rf.sort_values(
            by=['Composite_Score', 'final_rating', 'Predicted_Score', 'Initial_Fit_Score'], 
            ascending=[False, False, False, False]
        )  # REMOVED: .head(20)
        
        result = top_recommendations[
            ['id', 'Name', 'Category', 'Price', 'Predicted_Score', 'Match_Type', 
             'Initial_Fit_Score', 'Attribute_Matches', 'final_rating', 'Composite_Score']
        ].to_dict('records')
        
        print(json.dumps(result))
        
    except Exception as e:
        print(f"DEBUG: Error: {str(e)}", file=sys.stderr)
        print(f"DEBUG: Traceback: {traceback.format_exc()}", file=sys.stderr)
        print(json.dumps([]))

if __name__ == "__main__":
    main()