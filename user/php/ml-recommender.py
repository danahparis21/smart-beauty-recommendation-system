import sys
import json
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.preprocessing import LabelEncoder
import traceback

def calculate_score(row, user_input):
    score = 0

    # Skin Type match
    if str(row['skin_type']).title() == user_input['Skin_Type'] or str(row['skin_type']).title() in ['Any', 'None']:
        score += 0.2

    # Skin Tone match
    if str(row['skin_tone']).title() == user_input['Skin_Tone'] or str(row['skin_tone']).title() in ['Any', 'None']:
        score += 0.2

    # Undertone match
    product_undertone = str(row['undertone']).strip().title()
    user_undertone = user_input['Undertone'].strip().title()

    is_match = False
    if product_undertone == user_undertone or product_undertone in ['Any', 'All', '']:
        is_match = True
    elif product_undertone == 'Neutral' and user_undertone in ['Cool', 'Warm']:
        is_match = True
    elif user_undertone == "Don't Know":
        is_match = True

    if is_match:
        score += 0.1

    # FIXED: Skin concerns match - give points if ANY concern matches
    user_concerns = [c.strip().title() for c in user_input['Skin_Concerns']]
    
    if not user_concerns or 'None' in user_concerns:
        # If no concerns, give full points
        score += 0.15
    else:
        concern_mapping = {
            'Acne': 'acne',
            'Dryness': 'dryness', 
            'Dark Spots': 'dark_spots',
            'Aging': 'aging'
        }
        
        # Check if ANY concern matches the product
        any_concern_match = False
        for concern in user_concerns:
            col_name = concern_mapping.get(concern, '')
            if col_name and row.get(col_name, 0) == 1:
                any_concern_match = True
                break
        
        if any_concern_match:
            score += 0.15
        # Don't add points if no concerns match

    # FIXED: Preference match - check if product has user's preferred finish
    finish_mapping = {
        'Matte': 'matte',
        'Dewy': 'dewy',
        'Long-Lasting': 'long_lasting'
    }
    user_pref = user_input['Preference']
    
    # FIXED: Handle case sensitivity here too
    user_pref_title = user_pref.title()
    pref_col = finish_mapping.get(user_pref_title, '')
    
    if not pref_col:
        pref_col = finish_mapping.get(user_pref, '')
    
    if pref_col and row.get(pref_col, 0) == 1:
        score += 0.15

    return min(score, 1.0)

def score_to_match(p_predicted, p_initial_fit):
    if p_predicted >= 4.5 and p_initial_fit >= 0.7:
        return "💎 PERFECT MATCH"
    elif p_predicted >= 4.0:
        return "💖 CLOSE MATCH"
    elif p_predicted >= 3.0:
        return "🌸 NORMAL MATCH"
    else:
        return "⚠️ LOW RATING MATCH"

def analyze_attribute_matches(product, user_input):
    matches = {}
    
    # Skin Type match
    product_skin_type = str(product.get('skin_type', 'Any')).title()
    user_skin_type = user_input['Skin_Type']
    matches['skin_type'] = {
        'match': product_skin_type == user_skin_type or product_skin_type in ['Any', 'None'],
        'product_value': product_skin_type,
        'user_value': user_skin_type
    }
    
    # Skin Tone match
    product_skin_tone = str(product.get('skin_tone', 'Any')).title()
    user_skin_tone = user_input['Skin_Tone']
    matches['skin_tone'] = {
        'match': product_skin_tone == user_skin_tone or product_skin_tone in ['Any', 'None'],
        'product_value': product_skin_tone,
        'user_value': user_skin_tone
    }
    
    # Undertone match
    product_undertone = str(product.get('undertone', 'Any')).strip().title()
    user_undertone = user_input['Undertone'].strip().title()
    
    is_undertone_match = False
    if product_undertone == user_undertone or product_undertone in ['Any', 'All', '']:
        is_undertone_match = True
    elif product_undertone == 'Neutral' and user_undertone in ['Cool', 'Warm']:
        is_undertone_match = True
    elif user_undertone == "Don't Know":
        is_undertone_match = True
        
    matches['undertone'] = {
        'match': is_undertone_match,
        'product_value': product_undertone,
        'user_value': user_undertone
    }
    
    user_concerns = [c.strip().title() for c in user_input['Skin_Concerns']]
    concern_mapping = {
        'Acne': 'acne',
        'Dryness': 'dryness', 
        'Dark Spots': 'dark_spots',
        'Aging': 'aging'
    }
    
    concern_matches = {}
    overall_concern_match = False
    
    # If user has no concerns or selected "None"
    if not user_concerns or 'None' in user_concerns:
        concern_matches['No Concerns'] = {
            'match': True,
            'product_value': 'Any',
            'user_value': 'None'
        }
        overall_concern_match = True
    else:
        # Check each user concern
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
    
    # FIXED: Finish preference match - Show ALL finishes the product has
    finish_mapping = {
        'Matte': 'matte',
        'Dewy': 'dewy', 
        'Long-Lasting': 'long_lasting'
    }
    
    # Get all finishes that the product has
    product_finishes = []
    for finish_name, db_column in finish_mapping.items():
        if product.get(db_column, 0) == 1:
            product_finishes.append(finish_name)
    
    user_preferred_finish = user_input['Preference']
    
    # FIXED: Handle case sensitivity - convert user preference to title case for matching
    user_pref_title = user_preferred_finish.title()
    pref_col = finish_mapping.get(user_pref_title, '')
    
    # If not found with title case, try direct match
    if not pref_col:
        pref_col = finish_mapping.get(user_preferred_finish, '')
    
    has_preferred_finish = pref_col and product.get(pref_col, 0) == 1
    
    # FIXED: Ensure we never show "None" - use the actual product_finishes
    product_finish_text = ', '.join(product_finishes) if product_finishes else 'None'
    
    matches['finish'] = {
        'match': has_preferred_finish,  # This should now be True when product has user's preferred finish
        'product_value': product_finish_text,  # This should now show "Matte, Long-Lasting"
        'user_value': user_preferred_finish
    }
    
    return matches

def create_sample_data():
    """Create sample data for testing when no file is provided"""
    sample_products = [
        {
            'id': '1', 'Name': 'Sample Foundation', 'Category': 'Foundation', 
            'Price': 599.00, 'skin_type': 'Normal', 'skin_tone': 'Medium', 
            'undertone': 'Neutral', 'acne': 1, 'dryness': 0, 'dark_spots': 1,
            'matte': 1, 'dewy': 0, 'long_lasting': 1, 'user_rating': 4.5,
            'productrating': 4.5, 'has_personal_feedback': 0
        },
        {
            'id': '2', 'Name': 'Sample Lipstick', 'Category': 'Lipstick',
            'Price': 299.00, 'skin_type': 'Any', 'skin_tone': 'Any', 
            'undertone': 'Any', 'acne': 0, 'dryness': 0, 'dark_spots': 0,
            'matte': 0, 'dewy': 1, 'long_lasting': 1, 'user_rating': 4.2,
            'productrating': 4.2, 'has_personal_feedback': 0
        }
    ]
    
    sample_user_input = {
        'Skin_Type': 'Normal',
        'Skin_Tone': 'Medium', 
        'Undertone': 'Neutral',
        'Skin_Concerns': ['Acne', 'Dark Spots'],
        'Preference': 'Matte'
    }
    
    return {
        'user_input': sample_user_input,
        'products': sample_products
    }

def main():
    try:
        # REMOVE ALL DEBUG PRINT STATEMENTS TO STDOUT
        # Only output the final JSON
        
        # Check if we have a command line argument
        if len(sys.argv) < 2:
            # Use sample data for testing
            data = create_sample_data()
        else:
            # Read data from temp file
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
        
        # Label encoding
        label_encoders = {}
        for col in ['skin_type', 'skin_tone', 'undertone']:
            le = LabelEncoder()
            df_rf[col + '_enc'] = le.fit_transform(df_rf[col].astype(str))
            label_encoders[col] = le
        
        # Calculate Initial_Fit_Score
        df_rf['Initial_Fit_Score'] = df_rf.apply(lambda row: calculate_score(row, user_input), axis=1)
        
        # Prepare features
        feature_columns = ['skin_type_enc', 'skin_tone_enc', 'undertone_enc', 
                          'acne', 'dryness', 'dark_spots', 'matte', 'dewy', 'long_lasting',
                          'Initial_Fit_Score']
        
        # Add optional features if they exist
        if 'productrating' in df_rf.columns:
            feature_columns.append('productrating')
        if 'has_personal_feedback' in df_rf.columns:
            feature_columns.append('has_personal_feedback')
        
        # Ensure all features exist
        for feature in feature_columns:
            if feature not in df_rf.columns:
                df_rf[feature] = 0
        
        X = df_rf[feature_columns].fillna(0)
        y = df_rf['user_rating'].fillna(4.0)
        
        # Train model
        rf = RandomForestRegressor(n_estimators=50, random_state=42)
        rf.fit(X, y)
        
        # Predict
        df_rf['Predicted_Score'] = rf.predict(X)
        df_rf['Match_Type'] = df_rf.apply(
            lambda row: score_to_match(row['Predicted_Score'], row['Initial_Fit_Score']), 
            axis=1
        )
        
        # ✅ ADD ATTRIBUTE MATCHES HERE (INSIDE MAIN FUNCTION)
        df_rf['Attribute_Matches'] = df_rf.apply(
            lambda row: analyze_attribute_matches(row, user_input), 
            axis=1
        )
        
        # ✅ INCLUDE Attribute_Matches IN THE OUTPUT
        top_recommendations = df_rf.nlargest(12, 'Predicted_Score')[
            ['id', 'Name', 'Category', 'Price', 'Predicted_Score', 'Match_Type', 'Initial_Fit_Score', 'Attribute_Matches']
        ]
        
        # ONLY OUTPUT THE JSON - NO OTHER PRINT STATEMENTS
        result = top_recommendations.to_dict('records')
        print(json.dumps(result))
        
    except Exception as e:
        # On error, output empty array as JSON
        print(json.dumps([]))

if __name__ == "__main__":
    main()