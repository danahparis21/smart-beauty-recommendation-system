import cv2
import numpy as np
import base64
from flask import Flask, request, jsonify
from flask_cors import CORS
import colorsys
from sklearn.cluster import KMeans
import mediapipe as mp
import json
import requests
import time


app = Flask(__name__)
CORS(app)


def get_product_attributes_from_db(product_id):
    """Get product attributes from PHP API with better debugging"""
    try:
        php_endpoint = "http://php/user/php/get_product_attributes.php"
        
        print(f"🔍 Fetching product data for: {product_id}")
        response = requests.post(
            php_endpoint,
            json={'product_id': product_id},
            headers={'Content-Type': 'application/json'},
            timeout=10
        )
        
        print(f"📡 Response status: {response.status_code}")
        
        if response.status_code == 200:
            result = response.json()
            print(f"📦 Raw API response: {result}")
            
            if result.get('success') and 'product_attributes' in result:
                product_data = result['product_attributes']
                print(f"✅ Found product data: {product_data}")
                return product_data
            else:
                print(f"❌ API returned error: {result.get('error', 'Unknown error')}")
        else:
            print(f"❌ API returned status: {response.status_code}")
            
    except Exception as e:
        print(f"❌ Error calling PHP API: {e}")
        import traceback
        print(f"❌ Traceback: {traceback.format_exc()}")
    
    return None


@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({'status': 'healthy', 'service': 'lipstick-analysis'})

# Initialize MediaPipe Face Mesh
mp_face_mesh = mp.solutions.face_mesh
face_mesh = mp_face_mesh.FaceMesh(
    static_image_mode=True,
    max_num_faces=1,
    refine_landmarks=True,
    min_detection_confidence=0.5,
    min_tracking_confidence=0.5
)

def analyze_skin_tone_undertone(image):
    """Analyze skin tone and undertone from facial image - RELIABLE VERSION (with AWB)"""
    
    # --- DYNAMIC LIGHTING CORRECTION ---
    max_b = np.max(image[:, :, 0])
    max_g = np.max(image[:, :, 1])
    max_r = np.max(image[:, :, 2])
    
    gain_b = 255.0 / max_b
    gain_g = 255.0 / max_g
    gain_r = 255.0 / max_r
    
    image_corrected = image.astype(np.float32)
    image_corrected[:, :, 0] = np.clip(image_corrected[:, :, 0] * gain_b, 0, 255)
    image_corrected[:, :, 1] = np.clip(image_corrected[:, :, 1] * gain_g, 0, 255)
    image_corrected[:, :, 2] = np.clip(image_corrected[:, :, 2] * gain_r, 0, 255)
    image_corrected = image_corrected.astype(np.uint8)
    
    # --- START OF ORIGINAL ANALYSIS LOGIC ---
    try:
        # Convert corrected image to RGB for MediaPipe
        rgb_image = cv2.cvtColor(image_corrected, cv2.COLOR_BGR2RGB)
        
        # ADD RETRY LOGIC FOR MEDIAPIPE ERROR - PUT IT HERE
        max_retries = 2
        results = None
        
        for attempt in range(max_retries):
            try:
                # Process with MediaPipe
                results = face_mesh.process(rgb_image)
                break  # Success, break out of retry loop
            except Exception as mp_error:
                if "timestamp mismatch" in str(mp_error) and attempt < max_retries - 1:
                    print(f"🔄 MediaPipe timestamp error, retrying... (attempt {attempt + 1})")
                    time.sleep(0.1)  # Add this import at top: import time
                    continue  # Try again
                else:
                    print(f"❌ MediaPipe error, using fallback: {mp_error}")
                    return simple_fallback_analysis(image)  # ← USE FALLBACK
        
        # Check if we got results after retries
        if results is None or not results.multi_face_landmarks:
            print("❌ No face detected by MediaPipe after retries")
            return None
        
        # Get face landmarks
        landmarks = results.multi_face_landmarks[0]
        h, w = image.shape[:2]
        
        # Define cheek sampling points
        cheek_points = [117, 118, 119, 100, 47, 126, 209, 49, 50, 346, 347, 348, 329, 277, 355, 429, 279, 280]
        
        skin_pixels_bgr = []
        
        for point_idx in cheek_points:
            landmark = landmarks.landmark[point_idx]
            x = int(landmark.x * w)
            y = int(landmark.y * h)
            
            # Sample multiple pixels around each landmark for better accuracy
            for dx in [-2, 0, 2]:
                for dy in [-2, 0, 2]:
                    nx, ny = x + dx, y + dy
                    if 0 <= nx < w and 0 <= ny < h:
                        # SAMPLE from the CORRECTED image
                        skin_pixels_bgr.append(image_corrected[ny, nx]) 
        
        if len(skin_pixels_bgr) < 50: 
            print("❌ Not enough skin pixels extracted")
            return None
        
        skin_pixels_bgr = np.array(skin_pixels_bgr)
        
        # --- 1. SKIN TONE CLASSIFICATION (using HSV Value) ---
        hsv_pixels = cv2.cvtColor(skin_pixels_bgr.reshape(-1, 1, 3), cv2.COLOR_BGR2HSV).reshape(-1, 3)
        brightness = np.mean(hsv_pixels[:, 2]) 
                
        # 4-CATEGORY THRESHOLDS - FINAL VERSION
        if brightness < 120:
            tone = "Deep"      # V 0-120 (dark skin tones)
        elif brightness < 170:
            tone = "Tan"       # V 120-170 (medium-dark skin tones)  
        elif brightness < 220:
            tone = "Medium"    # V 170-220 (medium skin tones)
        else:
            tone = "Fair"      # V 220-255 (fair skin tones)

        print(f"🎨 Classified Tone: {tone} (HSV V: {brightness:.1f})")

        # --- 2. UNDERTONE CLASSIFICATION (using RGB Ratios) ---
        # Calculate means from the BGR array
        rgb_means = np.mean(skin_pixels_bgr, axis=0)
        b, g, r = rgb_means[0], rgb_means[1], rgb_means[2]  # OpenCV BGR order
        
        # Avoid division by zero
        if g == 0: g = 1
        if b == 0: b = 1
        
        # Calculate ratios (R is numerator for skin color analysis)
        rg_ratio = r / g
        rb_ratio = r / b
        
        # Stricter thresholds for Neutral/Warm separation (Warm skin has higher R vs G/B)
        if rg_ratio > 1.25 and rb_ratio > 1.15:
            undertone = "Warm"
        elif rg_ratio < 1.05 and rb_ratio < 1.05:
            undertone = "Cool"
        else:
            undertone = "Neutral"

        print(f"🔍 Undertone: {undertone} (R/G: {rg_ratio:.2f}, R/B: {rb_ratio:.2f})")
        
        # Prepare final output color (ALWAYS RGB)
        avg_rgb = [int(r), int(g), int(b)] # Use the floating point means for smooth conversion
        
        # Return results
        return {
            'tone': tone,
            'undertone': undertone,
            'brightness': float(brightness),
            'skin_color_rgb': avg_rgb, 
            'skin_color_hex': '#{:02x}{:02x}{:02x}'.format(avg_rgb[0], avg_rgb[1], avg_rgb[2]),
            'analysis_metrics': {
                'rg_ratio': float(rg_ratio),
                'rb_ratio': float(rb_ratio),
                'pixels_analyzed': len(skin_pixels_bgr),
                'detected_brightness': float(brightness)
            },
            'confidence': min(len(skin_pixels_bgr) / 100, 1.0)
        }
        
    except Exception as e:
        print(f"💥 Skin analysis error: {e}")
        # Don't print traceback for timestamp errors
        if "timestamp mismatch" not in str(e):
            import traceback
            print(f"Skin analysis traceback: {traceback.format_exc()}")
        return None
    
def simple_fallback_analysis(image):
    """Simple fallback when MediaPipe fails completely"""
    print("🔄 Using fallback skin analysis")
    
    # Calculate average color of the image
    avg_color = np.mean(image, axis=(0, 1))
    b, g, r = avg_color[0], avg_color[1], avg_color[2]
    
    # Simple brightness detection
    hsv = cv2.cvtColor(image, cv2.COLOR_BGR2HSV)
    brightness = np.mean(hsv[:, :, 2])
    
    # Simple tone classification
    if brightness < 100:
        tone = "Deep"
    elif brightness < 160:
        tone = "Medium"
    else:
        tone = "Fair"
    
    # Simple undertone estimation
    if r > max(g, b) + 15:
        undertone = "Warm"
    elif b > max(r, g) + 10:
        undertone = "Cool"
    else:
        undertone = "Neutral"
    
    return {
        'tone': tone,
        'undertone': undertone,
        'brightness': float(brightness),
        'skin_color_rgb': [int(r), int(g), int(b)],
        'skin_color_hex': '#{:02x}{:02x}{:02x}'.format(int(r), int(g), int(b)),
        'confidence': 0.4,  # Lower confidence for fallback
        'fallback_used': True
    }

def hex_to_rgb(hex_color):
    """Convert hex color to RGB"""
    hex_color = hex_color.lstrip('#')
    return tuple(int(hex_color[i:i+2], 16) for i in (0, 2, 4))

def calculate_color_compatibility(skin_tone_data, lipstick_hex, product_data=None):
    """Calculate compatibility using database labels OR fallback to algorithm"""
    try:
        # PREFERRED: Use database labels if available
        if product_data and 'SkinTone' in product_data and 'Undertone' in product_data:
            print("🎯 Using database compatibility analysis")
            result = calculate_compatibility_from_database(skin_tone_data, product_data)
            result['match_source'] = 'database'
            return result
        
        # FALLBACK: Use algorithmic approach
        print("🎯 Using algorithmic compatibility analysis")
        result = calculate_compatibility_algorithmic(skin_tone_data, lipstick_hex)
        result['match_source'] = 'algorithm'
        return result
        
    except Exception as e:
        print(f"Color compatibility error: {e}")
        return get_default_compatibility()

def calculate_compatibility_from_database(skin_tone_data, product_data):
    """Calculate compatibility based on database skin_tone and undertone labels"""
    user_tone = skin_tone_data['tone']
    user_undertone = skin_tone_data['undertone']
    
    # FIX: Use the correct field names from database (camelCase)
    compatible_tones = [t.strip() for t in product_data.get('SkinTone', '').split(',')]
    compatible_undertones = [u.strip() for u in product_data.get('Undertone', '').split(',')]
    
    print(f"🎯 Database analysis:")
    print(f"   User: {user_tone} skin, {user_undertone} undertone")
    print(f"   Product compatible tones: {compatible_tones}")
    print(f"   Product compatible undertones: {compatible_undertones}")
    
    score = 0
    max_score = 4  # 2 points for tone match, 2 points for undertone match
    
    # Check skin tone compatibility (2 points)
    if 'All' in compatible_tones or user_tone in compatible_tones:
        score += 2
        print(f"   ✅ Perfect tone match: +2 points")
    elif any(tone in compatible_tones for tone in ['Fair', 'Medium', 'Tan', 'Deep']):
        # Partial match if other specific tones are listed
        score += 1
        print(f"   ⚠️ Partial tone match: +1 point")
    
    # Check undertone compatibility (2 points)
    if 'All' in compatible_undertones or user_undertone in compatible_undertones:
        score += 2
        print(f"   ✅ Perfect undertone match: +2 points")
    elif 'Neutral' in compatible_undertones and user_undertone in ['Warm', 'Cool']:
        # Neutral undertone products often work with both warm and cool
        score += 1
        print(f"   ⚠️ Partial undertone match: +1 point")
    
    percentage = int((score / max_score) * 100)
    print(f"   📊 Final score: {score}/{max_score} = {percentage}%")
    
    # Generate recommendation based on score
    if percentage >= 75:
        recommendation = "Perfect match! This shade is specifically recommended for your skin tone."
        match_level = "excellent"
    elif percentage >= 50:
        recommendation = "Good match! This shade works well with your skin characteristics."
        match_level = "good"
    elif percentage >= 25:
        recommendation = "Decent match. This shade may work for your skin tone."
        match_level = "decent"
    else:
        recommendation = "This shade may not be the most flattering for your skin tone."
        match_level = "poor"
    
    return {
        'compatibility_score': percentage,
        'recommendation': recommendation,
        'match_level': match_level
    }

def calculate_compatibility_algorithmic(skin_tone_data, lipstick_hex):
    """Fallback to the original algorithmic approach"""
    try:
        lipstick_rgb = hex_to_rgb(lipstick_hex)
        skin_rgb = skin_tone_data['skin_color_rgb']
        
        # Convert to HSV for better color analysis
        skin_hsv = colorsys.rgb_to_hsv(skin_rgb[0]/255, skin_rgb[1]/255, skin_rgb[2]/255)
        lipstick_hsv = colorsys.rgb_to_hsv(lipstick_rgb[0]/255, lipstick_rgb[1]/255, lipstick_rgb[2]/255)
        
        compatibility_score = 0
        
        # Rule-based compatibility scoring
        tone = skin_tone_data['tone']
        undertone = skin_tone_data['undertone']
        
        # Brightness compatibility (contrast)
        brightness_diff = abs(skin_hsv[2] - lipstick_hsv[2])
        
        # Hue compatibility based on undertone
        hue_diff = min(abs(skin_hsv[0] - lipstick_hsv[0]), 1 - abs(skin_hsv[0] - lipstick_hsv[0]))
        
        # Compatibility rules
        if undertone == "Warm":
            # Warm undertones look good with warm lipsticks (reds, oranges, corals)
            if lipstick_hsv[0] < 0.1 or lipstick_hsv[0] > 0.9:  # Reds
                compatibility_score += 0.4
            elif 0.05 < lipstick_hsv[0] < 0.15:  # Oranges/Corals
                compatibility_score += 0.3
        else:  # Cool undertone
            # Cool undertones look good with cool lipsticks (pinks, berries, mauves)
            if 0.85 < lipstick_hsv[0] < 0.95:  # Pinks
                compatibility_score += 0.4
            elif 0.7 < lipstick_hsv[0] < 0.85:  # Berries/Mauves
                compatibility_score += 0.3
        
        # Tone-specific adjustments
        if tone in ["Deep", "Medium"]:
            # Deeper skin tones can handle more pigmented colors
            if lipstick_hsv[1] > 0.6:  # High saturation
                compatibility_score += 0.2
        else:  # Light/Fair
            # Lighter skin tones often look better with medium saturation
            if 0.3 < lipstick_hsv[1] < 0.7:
                compatibility_score += 0.2
        
        # Contrast adjustment - ensure lipstick is visible
        if 0.2 < brightness_diff < 0.6:
            compatibility_score += 0.2
        
        # Normalize score to 0-1 range
        compatibility_score = min(compatibility_score, 1.0)
        
        # Convert to percentage and get recommendation
        percentage = int(compatibility_score * 100)
        
        if percentage >= 80:
            recommendation = "Excellent match! This shade complements your skin tone perfectly."
            match_level = "excellent"
        elif percentage >= 60:
            recommendation = "Good match! This shade looks great with your skin tone."
            match_level = "good"
        elif percentage >= 40:
            recommendation = "Decent match. This shade works well with your skin tone."
            match_level = "decent"
        else:
            recommendation = "This shade may not be the most flattering for your skin tone."
            match_level = "poor"
        
        return {
            'compatibility_score': percentage,
            'recommendation': recommendation,
            'match_level': match_level,
            'color_analysis': {
                'brightness_contrast': brightness_diff,
                'hue_compatibility': hue_diff
            },
            'match_source': 'algorithm'
        }
        
    except Exception as e:
        print(f"Algorithmic compatibility error: {e}")
        return get_default_compatibility()

def get_default_compatibility():
    """Return default compatibility when analysis fails"""
    return {
        'compatibility_score': 50,
        'recommendation': 'Unable to analyze color compatibility.',
        'match_level': 'unknown',
        'match_source': 'default'
    }

@app.route('/analyze-lipstick', methods=['POST', 'OPTIONS'])
def analyze_lipstick():
    """Main endpoint for lipstick analysis"""
    try:
        # Handle CORS preflight
        if request.method == 'OPTIONS':
            return '', 200

        print("📨 Received analysis request")
        data = request.get_json()

        if not data:
            print("❌ No JSON data received")
            return jsonify({'error': 'No JSON data received'}), 400

        image_data = data.get('image')
        lipstick_hex = data.get('lipstick_color')
        product_id = data.get('product_id')  # NEW: Get product ID

        print(f"🎨 Lipstick color: {lipstick_hex}")
        print(f"📦 Product ID: {product_id}")
        print(f"🖼️ Image data received: {len(image_data) if image_data else 0} chars")

        if not image_data or not lipstick_hex:
            print("❌ Missing image or lipstick color")
            return jsonify({'error': 'Missing image or lipstick color'}), 400

        # Decode base64 image
        try:
            print("🔍 Decoding base64 image...")

            # Handle both formats: with and without data URI prefix
            if ',' in image_data:
                image_data = image_data.split(',')[1]

            image_bytes = base64.b64decode(image_data)
            np_arr = np.frombuffer(image_bytes, np.uint8)
            image = cv2.imdecode(np_arr, cv2.IMREAD_COLOR)

            if image is None:
                print("❌ Failed to decode image")
                return jsonify({'error': 'Invalid image data'}), 400

            print(f"✅ Image decoded: {image.shape}")

        except Exception as e:
            print(f"❌ Image decoding error: {e}")
            return jsonify({'error': f'Image decoding failed: {str(e)}'}), 400

        # Analyze skin tone
        print("🔍 Analyzing skin tone...")
        skin_analysis = analyze_skin_tone_undertone(image)

        if not skin_analysis:
            print("❌ No face detected")
            return jsonify({
                'success': False,
                'message': 'Could not detect face clearly. Please upload a front-facing photo with good lighting.'
            })

        print(f"✅ Skin analysis: {skin_analysis['tone']} skin, {skin_analysis['undertone']} undertone")

        # Get product attributes from database if product_id is provided
        product_data = None
        match_source = 'algorithm'  # Default

        if product_id and product_id != 'null' and product_id != 'undefined':
            print(f"📊 Fetching product attributes for '{product_id}'...")
            product_data = get_product_attributes_from_db(product_id)
            if product_data:
                print(f"✅ Using database attributes: {product_data}")
                match_source = 'database'
            else:
                print("⚠️ No database attributes found, using algorithmic approach")
        else:
            print("ℹ️ No valid product ID provided, using algorithmic approach")

        # Calculate color compatibility
        print("🎨 Calculating color compatibility...")
        compatibility = calculate_color_compatibility(skin_analysis, lipstick_hex, product_data)
        
        # Ensure match_source is set
        if 'match_source' not in compatibility:
            compatibility['match_source'] = match_source
        
        print(f"✅ Compatibility score: {compatibility['compatibility_score']}%")
        print(f"🔍 Final match source: {compatibility.get('match_source')}")

        return jsonify({
            'success': True,
            'skin_analysis': skin_analysis,
            'color_compatibility': compatibility,
            'lipstick_color': lipstick_hex,
            'match_source': compatibility.get('match_source', 'algorithm')
        })

    except Exception as e:
        print(f"💥 Analysis error: {e}")
        import traceback
        print(f"💥 Stack trace: {traceback.format_exc()}")
        return jsonify({'success': False, 'error': str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)