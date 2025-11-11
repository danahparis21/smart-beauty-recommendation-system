import sys
import json
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.preprocessing import LabelEncoder

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

    # Skin concerns match
    user_concerns = [c.strip().title() for c in user_input['Skin_Concerns']]
    
    if user_concerns == ['None'] or not user_concerns:
        score += 0.15
    else:
        concern_mapping = {
            'Acne': 'acne',
            'Dryness': 'dryness', 
            'Dark Spots': 'dark_spots'
        }
        for concern in user_concerns:
            col_name = concern_mapping.get(concern, '')
            if col_name and row.get(col_name, 0) == 1:
                score += 0.15

    # Preference match
    pref_mapping = {
        'Matte': 'matte',
        'Dewy': 'dewy',
        'Long-Lasting': 'long_lasting'
    }
    pref_col = pref_mapping.get(user_input['Preference'], '')
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
        # Check if we have a command line argument
        if len(sys.argv) < 2:
            print("No input file provided, using sample data for testing...", file=sys.stderr)
            data = create_sample_data()
        else:
            # Read data from temp file
            with open(sys.argv[1], 'r') as f:
                data = json.load(f)
        
        user_input = data['user_input']
        products = data['products']
        
        print(f"Processing {len(products)} products for user: {user_input}", file=sys.stderr)
        
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
        
        # Return top recommendations
        top_recommendations = df_rf.nlargest(12, 'Predicted_Score')[
            ['id', 'Name', 'Category', 'Price', 'Predicted_Score', 'Match_Type', 'Initial_Fit_Score']
        ]
        
        print(f"Generated {len(top_recommendations)} recommendations", file=sys.stderr)
        print(json.dumps(top_recommendations.to_dict('records')))
        
    except Exception as e:
        print(f"Error in ML script: {str(e)}", file=sys.stderr)
        import traceback
        print(f"Traceback: {traceback.format_exc()}", file=sys.stderr)
        print(json.dumps([]))

if __name__ == "__main__":
    main()