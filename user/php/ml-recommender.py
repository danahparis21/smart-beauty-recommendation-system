import sys
import json
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.preprocessing import LabelEncoder
import traceback

# --- HELPER FUNCTIONS FOR CALCULATE_SCORE ---

def check_skin_type_match(row, user_input):
    product_type = str(row['skin_type']).strip().title()
    user_type = user_input['Skin_Type'].strip().title()
    return (
        product_type == user_type or 
        product_type in ['Any', 'All', 'None', 'N/A'] or
        user_type in ["Don't Care", "Any"] # FIXED: Check for "Don't Care"
    )

def check_skin_tone_match(row, user_input):
    product_tone = str(row['skin_tone']).strip().title()
    user_tone = user_input['Skin_Tone'].strip().title()
    
    if (product_tone in ['Any', 'All', 'None', 'N/A'] or
        user_tone in ["Don't Care", "Any"]): # FIXED: Check for "Don't Care"
        return True
    
    product_tones = [tone.strip().title() for tone in product_tone.split(',')]
    return user_tone in product_tones

def check_undertone_match(row, user_input):
    product_undertone = str(row['undertone']).strip().title()
    user_undertone = user_input['Undertone'].strip().title()
    
    # Normalize user undertone
    undertone_mapping = {
        "Not-Sure": "Don't Know", "Not Sure": "Don't Know", 
        "Dont Know": "Don't Know", "Dont-Care": "Don't Care"
    }
    normalized_user_undertone = undertone_mapping.get(user_undertone, user_undertone)

    if (product_undertone == normalized_user_undertone or 
        product_undertone in ['Any', 'All', ''] or
        normalized_user_undertone in ["Don't Know", "Don't Care", "Not Sure"]): # FIXED: Includes Don't Care
        return True
        
    # Neutral is often considered compatible with Cool/Warm
    elif product_undertone == 'Neutral' and normalized_user_undertone in ['Cool', 'Warm']:
        return True
        
    return False

def check_concerns_match(row, user_input):
    user_concerns = [c.strip().title() for c in user_input['Skin_Concerns']]
    
    if not user_concerns or 'None' in user_concerns:
        return True # Match if user has no concerns
    
    concern_mapping = {
        'Acne': 'acne', 'Dryness': 'dryness', 
        'Dark Spots': 'dark_spots', 'Aging': 'aging'
    }
    
    # Only need to match ONE concern
    for concern in user_concerns:
        col_name = concern_mapping.get(concern, '')
        if col_name and row.get(col_name, 0) == 1:
            return True
            
    return False

def check_finish_match(row, user_input):
    finish_mapping = {
        'Matte': 'matte', 'Dewy': 'dewy', 'Long-Lasting': 'long_lasting'
    }
    user_pref = user_input['Preference'].strip().title() # Normalize user pref
    
    if user_pref in ["Don't Care", "Any"]: # FIXED: Handle "Don't Care"
        return True
    
    user_pref_title = user_pref
    pref_col = finish_mapping.get(user_pref_title, '')
    
    # Check if the product has the preferred finish
    return pref_col and row.get(pref_col, 0) == 1

def calculate_score(row, user_input):
    product_category = str(row.get('Category', '')).lower()
    
    # Define category-specific behavior (Updated/Fixed Logic)
    is_truly_universal = any(word in product_category for word in ['tool', 'brush', 'applicator', 'brow', 'lash', 'nails', 'hair care', 'contact lense', 'body care'])
    is_face_care = any(word in product_category for word in ['face care', 'serum', 'moisturizer', 'treatment', 'cream', 'lotion', 'toner'])
    is_lipstick = 'lipstick' in product_category or 'liptint' in product_category
    is_color_cosmetic = any(word in product_category for word in ['blush', 'foundation', 'concealer', 'powder', 'highlighter', 'eyeshadow', 'eyeliner', 'mascara'])
    
    # 1. TRULY UNIVERSAL PRODUCTS (e.g., Tools)
    if is_truly_universal:
        base_score = 0.8  
        finish_match = check_finish_match(row, user_input)
        return base_score + (0.1 if finish_match else 0) 
        
    # 2. FACE CARE (Skin Type/Concerns are primary)
    elif is_face_care:
        score = 0
        # Weights for face care: Skin Type (40), Concerns (40), Finish (10), Base (10)
        score += 0.40 if check_skin_type_match(row, user_input) else 0
        score += 0.40 if check_concerns_match(row, user_input) else 0 # FIXED: Ensure concerns are weighted
        score += 0.10 if check_finish_match(row, user_input) else 0
        return min(score + 0.10, 1.0) 
        
    # 3. LIPSTICK/LIPS (Tone/Undertone are primary)
    elif is_lipstick:
        score = 0
        # Weights for lipstick: Skin Tone (40), Undertone (40), Finish (10), Base (10)
        score += 0.40 if check_skin_tone_match(row, user_input) else 0
        score += 0.40 if check_undertone_match(row, user_input) else 0
        score += 0.10 if check_finish_match(row, user_input) else 0
        return min(score + 0.10, 1.0)
    
    # 4. COLOR COSMETICS (Needs most attributes - Foundation, Blush, etc.)
    elif is_color_cosmetic:
        score = 0
        # Weights: Skin Type(25), Skin Tone(25), Undertone(15), Concerns(15), Finish(10), Base(10)
        score += 0.25 if check_skin_type_match(row, user_input) else 0
        score += 0.25 if check_skin_tone_match(row, user_input) else 0
        score += 0.15 if check_undertone_match(row, user_input) else 0
        score += 0.15 if check_concerns_match(row, user_input) else 0 # FIXED: Ensure concerns are weighted
        score += 0.10 if check_finish_match(row, user_input) else 0
        return min(score + 0.10, 1.0) 
        
    # 5. FALLBACK / OTHER CATEGORIES
    else:
        score = 0
        score += 0.20 if check_skin_type_match(row, user_input) else 0
        score += 0.20 if check_skin_tone_match(row, user_input) else 0
        score += 0.15 if check_undertone_match(row, user_input) else 0
        score += 0.15 if check_concerns_match(row, user_input) else 0
        score += 0.10 if check_finish_match(row, user_input) else 0
        return min(score + 0.20, 1.0)

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

def get_match_quality_badge(initial_fit_score):
    """NEW: Badge based purely on attribute compatibility - using original names"""
    if initial_fit_score >= 0.95: 
        return "PERFECT MATCH"
    elif initial_fit_score >= 0.75: 
        return "CLOSE MATCH" 
    elif initial_fit_score >= 0.55: 
        return "NORMAL MATCH"
    elif initial_fit_score >= 0.35: # Min score for display (LOW RATING MATCH)
        return "LOW RATING MATCH"
    else: 
        return "TOO LOW MATCH" # Products below this score will be filtered out

def get_rating_quality_badge(final_rating, has_personal_feedback, user_rating=None, productrating=None):
    # Check if product truly has no ratings (both user_rating and productrating are null/0)
    is_truly_unrated = (
        (user_rating is None or pd.isna(user_rating) or user_rating == 0) and
        (productrating is None or pd.isna(productrating) or productrating == 0)
    )
    
    if is_truly_unrated:
        return "⭐ UNRATED"
    elif final_rating >= 4.5:
        return "🔥 TOP RATED" if has_personal_feedback else "🌟 HIGHLY RATED"
    elif final_rating >= 4.0:
        return "👍 WELL RATED" if has_personal_feedback else "📊 GOOD RATING"
    elif final_rating >= 3.0:
        return "📈 AVERAGE RATING"
    else:
        return "💤 LOW RATING"

def analyze_attribute_matches(product, user_input):
    matches = {}
    
    # Skin Type match
    product_skin_type = str(product.get('skin_type', 'Any')).strip().title()
    user_skin_type = user_input['Skin_Type'].strip().title()
    
    skin_type_match = (
        product_skin_type == user_skin_type or 
        product_skin_type in ['Any', 'All', 'None', 'N/A'] or
        user_skin_type in ["Don't Care", "Any"] # FIXED: Check for "Don't Care"
    )
    
    matches['skin_type'] = {
        'match': skin_type_match,
        # FIXED: Use 'All' if product_skin_type is an empty string
        'product_value': product_skin_type if product_skin_type else 'All',
        'user_value': user_skin_type
    }
    
    # Skin Tone match
    product_skin_tone = str(product.get('skin_tone', 'Any')).strip().title()
    user_skin_tone = user_input['Skin_Tone'].strip().title()
    
    skin_tone_match = False
    if (product_skin_tone in ['Any', 'All', 'None', 'N/A'] or
        user_skin_tone in ["Don't Care", "Any"]): # FIXED: Check for "Don't Care"
        skin_tone_match = True
    else:
        product_tones = [tone.strip().title() for tone in product_skin_tone.split(',')]
        skin_tone_match = user_skin_tone in product_tones
    
    matches['skin_tone'] = {
        'match': skin_tone_match,
        'product_value': product_skin_tone if product_skin_tone else 'All',
        'user_value': user_skin_tone
    }
    
    # Undertone match - FIXED: Handle "Don't Care"
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
        'product_value': product_undertone if product_undertone else 'Any',
        'user_value': user_undertone  # Keep original for display
    }
    
    # Skin Concerns - FIXED: Proper display logic
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
        product_addresses = []
        for concern in user_concerns:
            col_name = concern_mapping.get(concern, '')
            if col_name:
                product_has_concern = product.get(col_name, 0) == 1
                if product_has_concern:
                    product_addresses.append(concern)
                    any_concern_matched = True
        
        # FIXED: Create a single match entry for display
        concern_matches['Skin Concerns'] = {
            'match': any_concern_matched,
            'product_value': ', '.join(product_addresses) if product_addresses else 'None',
            'user_value': ', '.join(user_concerns)
        }
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
    
    user_preferred_finish = user_input['Preference'].strip().title()
    
    # If user doesn't care, it's always a match (match=True)
    if user_preferred_finish in ["Don't Care", "Any"]:
        has_preferred_finish = True
    else:
        user_pref_title = user_preferred_finish
        pref_col = finish_mapping.get(user_pref_title, '')
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
            'id': 'FND001', 'Name': 'Perfect Foundation', 'Category': 'Foundation', 
            'Price': 850.00, 'skin_type': 'Oily', 'skin_tone': 'Medium', 
            'undertone': 'Neutral', 'acne': 0, 'dryness': 0, 'dark_spots': 1, # Addresses Dark Spots
            'matte': 1, 'dewy': 0, 'long_lasting': 1, 'user_rating': 4.8,
            'productrating': 4.8, 'has_personal_feedback': 1
        },
        {
            'id': 'CON002', 'Name': 'Mocallure Concealer', 'Category': 'Concealer',
            'Price': 180.00, 'skin_type': 'Any', 'skin_tone': 'Fair', 
            'undertone': 'Cool', 'acne': 0, 'dryness': 0, 'dark_spots': 0,
            'matte': 0, 'dewy': 0, 'long_lasting': 0, 'user_rating': 0,
            'productrating': 2.5, 'has_personal_feedback': 0
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
        'Skin_Type': 'oily',
        'Skin_Tone': 'medium', 
        'Undertone': 'neutral',
        'Skin_Concerns': ['dark-spots'],
        'Preference': 'matte'
    }
    
    return {
        'user_input': sample_user_input,
        'products': sample_products
    }

def create_ohe_features(df):
    df_ohe = df.copy()
    
    # Handle skin_tone (multi-value)
    skin_tone_dummies = df_ohe['skin_tone'].str.get_dummies(sep=',').add_prefix('skin_tone_')
    
    # Handle single-value columns
    skin_type_dummies = pd.get_dummies(df_ohe['skin_type'], prefix='skin_type')
    undertone_dummies = pd.get_dummies(df_ohe['undertone'], prefix='undertone')
    
    # Combine all OHE features
    all_ohe = pd.concat([skin_tone_dummies, skin_type_dummies, undertone_dummies], axis=1)
    
    # Merge with original dataframe
    df_result = pd.concat([df_ohe, all_ohe], axis=1)
    return df_result, all_ohe.columns.tolist()

def filter_recommendations(df_rf, max_recommendations=500, min_score=0.35, min_match_type_level="NORMAL MATCH"):
    """
    Filter to show only meaningful recommendations.
    max_recommendations is now a soft limit, min_score is the hard filter.
    """
    
    # FIXED: Hard filter out very low matches (e.g., TOO LOW MATCH)
    
    # Map match badges to a numerical value for comparison
    match_level_map = {
        "PERFECT MATCH": 4, 
        "CLOSE MATCH": 3, 
        "NORMAL MATCH": 2, 
        "LOW RATING MATCH": 1, 
        "TOO LOW MATCH": 0
    }
    
    min_level = match_level_map.get(min_match_type_level, 2) # Default to NORMAL MATCH (2)
    
    # Filter based on the Match_Type level
    filtered = df_rf[df_rf['Match_Type'].apply(lambda x: match_level_map.get(x, 0) >= min_level)].copy()
    
    # If using Initial_Fit_Score for the filter:
    # filtered = df_rf[df_rf['Initial_Fit_Score'] >= min_score].copy()
    
    # If still too many (shouldn't be an issue now, but kept for safety), take top N by composite score
    if len(filtered) > max_recommendations:
        # Use nlargest if the list is too long, relying on Composite_Score
        filtered = filtered.nlargest(max_recommendations, 'Composite_Score')
    
    # FIXED: Return ALL available results that pass the filter
    return filtered


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
        df_rf['final_rating'] = df_rf['final_rating'].fillna(3.5)
        
        # Ensure ratings are within reasonable range
        df_rf['final_rating'] = df_rf['final_rating'].clip(1.0, 5.0)
        
        # Phase 2: One-Hot Encoding (OHE) - Replaces Label Encoding
        df_rf, ohe_features = create_ohe_features(df_rf)
        
        # Calculate Initial_Fit_Score
        df_rf['Initial_Fit_Score'] = df_rf.apply(lambda row: calculate_score(row, user_input), axis=1)
        
        # NEW: Add Match Quality Badge (based purely on attributes)
        df_rf['Match_Type'] = df_rf['Initial_Fit_Score'].apply(get_match_quality_badge)

        # NEW: Add Rating Quality Badge (based on actual ratings)
        df_rf['Rating_Quality'] = df_rf.apply(
            lambda row: get_rating_quality_badge(
                row['final_rating'], 
                row.get('has_personal_feedback', 0),
                row.get('user_rating'),
                row.get('productrating')
            ), 
            axis=1
        )
        
        ohe_features = [col for col in df_rf.columns if any(p in col for p in ['skin_tone_', 'skin_type_', 'undertone_'])]

        # Define the final features for the Random Forest
        feature_columns = ohe_features + [
            'acne', 'dryness', 'dark_spots', 'aging', # All concerns must be included
            'matte', 'dewy', 'long_lasting', 
            'Initial_Fit_Score', 
            'final_rating' 
        ]

        # Ensure all features exist (especially for the concerns)
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
        # Check if there are enough samples to train the model
        if len(df_rf) > 2:
             rf.fit(X, y)
             # Predict
             df_rf['Predicted_Score'] = rf.predict(X)
        else:
            # Fallback for very small datasets
            df_rf['Predicted_Score'] = df_rf['final_rating']

        # Add attribute matches
        df_rf['Attribute_Matches'] = df_rf.apply(
            lambda row: analyze_attribute_matches(row, user_input), 
            axis=1
        )
        
        # Add category priority
        df_rf['Category_Priority'] = df_rf['Category'].apply(get_category_priority)
        
        # ENHANCED SORTING: Create Composite Score
        df_rf['Composite_Score'] = (
            df_rf['Initial_Fit_Score'] * 0.4 + 
            df_rf['Predicted_Score'] * 0.3 + 
            df_rf['final_rating'] * 0.3 
        )
        
        # Sort ALL recommendations
        top_recommendations = df_rf.sort_values(
            by=['Composite_Score', 'final_rating', 'Initial_Fit_Score', 'Predicted_Score'], 
            ascending=[False, False, False, False]
        ) 
        
        # FIXED: Apply filtering to remove low-quality matches
        # Products must be at least NORMAL MATCH or better.
        filtered_recommendations = filter_recommendations(
            top_recommendations, 
            max_recommendations=500, # Max available, but limited by filter
            min_match_type_level="NORMAL MATCH" 
        )
        
        # Use the filtered results
        result = filtered_recommendations[
            ['id', 'Name', 'Category', 'Price', 'Predicted_Score', 
            'Match_Type', 'Rating_Quality',
            'Initial_Fit_Score', 'Attribute_Matches', 'final_rating', 'Composite_Score']
        ].to_dict('records')
        
        print(json.dumps(result))
        
    except Exception as e:
        print(f"DEBUG: Error: {str(e)}", file=sys.stderr)
        print(f"DEBUG: Traceback: {traceback.format_exc()}", file=sys.stderr)
        print(json.dumps([]))

if __name__ == "__main__":
    main()