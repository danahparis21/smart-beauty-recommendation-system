import cv2
import numpy as np
import mediapipe as mp
import base64
import io
from PIL import Image


class LipstickApplicator:
    def __init__(self):
        self.mp_face_mesh = mp.solutions.face_mesh
        self.face_mesh = self.mp_face_mesh.FaceMesh(
            static_image_mode=True,
            max_num_faces=1,
            refine_landmarks=True,
            min_detection_confidence=0.5,
            min_tracking_confidence=0.5
        )
        
        # Define lip landmarks (more comprehensive)
        self.LIP_LANDMARKS = {
            'upper_outer': [61, 84, 85, 86, 87, 88, 89, 90, 91, 146, 77, 76, 62],
            'lower_outer': [61, 146, 91, 181, 84, 17, 314, 405, 320, 307, 308, 324, 318],
            'upper_inner': [78, 95, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106],
            'lower_inner': [107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119]
        }
    
    def base64_to_image(self, base64_string):
        """Convert base64 string to OpenCV image"""
        if ',' in base64_string:
            base64_string = base64_string.split(',')[1]
        
        image_data = base64.b64decode(base64_string)
        image = Image.open(io.BytesIO(image_data))
        return cv2.cvtColor(np.array(image), cv2.COLOR_RGB2BGR)
    
    def image_to_base64(self, image):
        """Convert OpenCV image to base64 string"""
        _, buffer = cv2.imencode('.jpg', image)
        return base64.b64encode(buffer).decode('utf-8')
    
    def get_lip_mask(self, landmarks, image_shape):
        """Create precise lip mask using all lip landmarks"""
        h, w = image_shape[:2]
        
        # Get all lip points
        all_lip_points = []
        for key in self.LIP_LANDMARKS:
            for landmark_idx in self.LIP_LANDMARKS[key]:
                landmark = landmarks[landmark_idx]
                x = int(landmark.x * w)
                y = int(landmark.y * h)
                all_lip_points.append([x, y])
        
        # Create convex hull for outer lip boundary
        all_lip_points = np.array(all_lip_points)
        hull = cv2.convexHull(all_lip_points)
        
        # Create mask
        mask = np.zeros((h, w), dtype=np.uint8)
        cv2.fillConvexPoly(mask, hull, 255)
        
        # Smooth the mask edges
        mask = cv2.GaussianBlur(mask, (5, 5), 0)
        
        return mask, hull
    
    def apply_lipstick(self, image, hex_color, opacity=0.8):
        """Apply lipstick to image with given hex color"""
        try:
            # Convert hex to BGR
            hex_color = hex_color.lstrip('#')
            rgb = tuple(int(hex_color[i:i+2], 16) for i in (0, 2, 4))
            lipstick_color = (rgb[2], rgb[1], rgb[0])  # RGB to BGR
            
            # Convert to RGB for MediaPipe
            rgb_image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
            results = self.face_mesh.process(rgb_image)
            
            if not results.multi_face_landmarks:
                return None
            
            landmarks = results.multi_face_landmarks[0].landmark
            
            # Get lip mask
            lip_mask, lip_hull = self.get_lip_mask(landmarks, image.shape)
            
            # Create lipstick layer
            lipstick_layer = np.zeros_like(image)
            lipstick_layer[lip_mask > 0] = lipstick_color
            
            # Blend with original image
            alpha = opacity
            beta = 1 - alpha
            
            # Apply only to lip area
            result = image.copy()
            lip_area = lip_mask > 0
            result[lip_area] = cv2.addWeighted(
                lipstick_layer[lip_area], alpha, 
                image[lip_area], beta, 0
            )
            
            # Add subtle shine effect
            result = self.add_lip_shine(result, lip_mask, lip_hull)
            
            return result
            
        except Exception as e:
            print(f"Error applying lipstick: {e}")
            return None
    
    def add_lip_shine(self, image, lip_mask, lip_hull):
        """Add realistic shine effect to lips"""
        h, w = image.shape[:2]
        
        # Create shine mask (center of lips)
        shine_mask = np.zeros((h, w), dtype=np.uint8)
        
        # Get lip bounding box
        x, y, w_hull, h_hull = cv2.boundingRect(lip_hull)
        center_x = x + w_hull // 2
        center_y = y + h_hull // 2
        
        # Create elliptical shine in center
        shine_radius_x = int(w_hull * 0.3)
        shine_radius_y = int(h_hull * 0.15)
        cv2.ellipse(shine_mask, (center_x, center_y - h_hull//8), 
                   (shine_radius_x, shine_radius_y), 0, 0, 360, 255, -1)
        
        # Blend shine with original image
        shine_strength = 0.3
        image_with_shine = image.copy()
        shine_area = (shine_mask > 0) & (lip_mask > 0)
        
        # Lighten the shine area
        image_with_shine[shine_area] = cv2.addWeighted(
            image_with_shine[shine_area], 1 - shine_strength,
            np.array([255, 255, 255], dtype=np.uint8), shine_strength, 0
        )
        
        return image_with_shine

# Flask endpoint for lipstick application
from flask import Flask, request, jsonify

app = Flask(__name__)
lipstick_applicator = LipstickApplicator()

@app.route('/apply-lipstick', methods=['POST'])
def apply_lipstick_endpoint():
    try:
        data = request.get_json()
        image_data = data.get('image')
        lipstick_color = data.get('lipstick_color')
        
        if not image_data or not lipstick_color:
            return jsonify({'success': False, 'error': 'Missing image or color'})
        
        # Convert base64 to image
        image = lipstick_applicator.base64_to_image(image_data)
        
        # Apply lipstick
        result_image = lipstick_applicator.apply_lipstick(image, lipstick_color)
        
        if result_image is not None:
            # Convert back to base64
            result_base64 = lipstick_applicator.image_to_base64(result_image)
            
            return jsonify({
                'success': True,
                'image': f"data:image/jpeg;base64,{result_base64}",
                'message': 'Lipstick applied successfully'
            })
        else:
            return jsonify({'success': False, 'error': 'Failed to apply lipstick'})
            
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5001, debug=False)