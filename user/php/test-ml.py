import sys
import os

# Add the current directory to Python path
sys.path.append(os.path.dirname(__file__))

from ml_recommender import main

if __name__ == "__main__":
    print("Testing ML recommender directly...")
    main()