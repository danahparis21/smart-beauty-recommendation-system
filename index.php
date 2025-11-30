<?php
// index.php - Beauty & Blessed Landing Page
$store_name = 'Beauty & Blessed';
$tagline = "Invest in yourself — reveal the beauty that's already within";
$location = 'Brgy. 4, C. Alvarez Street, Nasugbu, Batangas';
$contact = '+63 966 944 5591';
$hours = '9:00 AM – 9:00 PM (Closed on Saturdays)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Blessence - Premium Makeup & Beauty Products</title>
    <link rel="icon" href="admin/images/mainlogo1.png" type="image/png">
    <meta name="description" content="Invest in yourself with Beauty & Blessed. Discover premium Malaysian cosmetics, virtual try-on technology, and personalized beauty recommendations in Nasugbu, Batangas.">
    <meta name="author" content="Blessence">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta property="og:title" content="Blessence - Premium Makeup & Beauty Products">
<meta property="og:description" content="Invest in yourself — reveal the beauty that's already within. Shop premium Malaysian cosmetics and beauty products.">
<meta property="og:type" content="website">
<meta property="og:url" content="https://blessence.site/">
<meta property="og:image" content="https://blessence.site/admin/images/mainlogo1.png">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Blessence - Premium Makeup & Beauty Products">
<meta name="twitter:description" content="Invest in yourself — reveal the beauty that's already within.">
<meta name="twitter:image" content="https://blessence.site/admin/images/mainlogo1.png">
    

    <style>
        /* CSS Variables using your HSL design system */
        :root {
            /* Core Colors */
            --background: 0 0% 100%;
            --foreground: 330 5% 20%;
            --card: 0 0% 100%;
            --card-foreground: 330 5% 20%;
            --primary: 330 81% 60%;
            --primary-foreground: 0 0% 100%;
            --primary-glow: 330 100% 75%;
            --secondary: 45 90% 65%;
            --secondary-foreground: 330 5% 20%;
            --muted: 330 20% 96%;
            --muted-foreground: 330 5% 45%;
            --accent: 330 100% 95%;
            --accent-foreground: 330 81% 60%;
            --border: 330 20% 90%;
            --input: 330 20% 90%;
            --ring: 330 81% 60%;
            --primary-pink: #ff69b4;

            /* Beauty Brand Colors */
            --pink-soft: 330 100% 98%;
            --pink-light: 330 100% 92%;
            --gold: 45 90% 65%;
            --gold-light: 45 100% 85%;
            
            /* Gradients */
            --gradient-primary: linear-gradient(135deg, hsl(330 81% 60%), hsl(330 100% 75%));
            --gradient-gold: linear-gradient(135deg, hsl(45 90% 65%), hsl(45 100% 85%));
            --gradient-soft: linear-gradient(135deg, hsl(0 0% 100%), hsl(330 100% 98%));
            --gradient-overlay: linear-gradient(135deg, rgba(255, 105, 180, 0.1), rgba(212, 175, 55, 0.1));
            --gradient-text: linear-gradient(135deg, hsl(330 81% 60%), hsl(45 90% 65%));
            
            /* Shadows */
            --shadow-sm: 0 2px 8px rgba(255, 105, 180, 0.1);
            --shadow-md: 0 8px 24px rgba(255, 105, 180, 0.15);
            --shadow-lg: 0 16px 48px rgba(255, 105, 180, 0.2);
            --shadow-glow: 0 0 40px rgba(255, 105, 180, 0.3);
            
            /* Transitions */
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            
            /* Border Radius */
            --radius: 1rem;
            --pink-primary: #FF6B9D;
    --pink-dark: #FF4785;
    --pink-light: #FFB6C1;
    --dark: #333;
        }

        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            border-color: hsl(var(--border));
        }

        html, body {
            width: 100%;
            overflow-x: hidden;
            position: relative;
            scroll-behavior: smooth;
        }

        body {
            background: hsl(var(--background));
            color: hsl(var(--foreground));
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
            background-attachment: fixed;
        }

        /* Utility Classes */
        .text-gradient {
            background: var(--gradient-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .text-gradient-gold {
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .bg-gradient-primary {
            background: var(--gradient-primary);
        }
        
        .bg-gradient-soft {
            background: var(--gradient-soft);
        }
        
        .bg-gradient-gold {
            background: var(--gradient-gold);
        }
        
        .shadow-glow {
            box-shadow: var(--shadow-glow);
        }
        
        .transition-smooth {
            transition: var(--transition-smooth);
        }
        
        .transition-bounce {
            transition: var(--transition-bounce);
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }

        /* Typography */
        .section-title {
            font-size: 3.5rem;
            font-weight: 700;
            background: var(--gradient-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-family: 'Playfair Display', serif;
            margin-bottom: 1rem;
            line-height: 1.1;
        }

        .section-subtitle {
            font-size: 1.25rem;
            color: hsl(var(--muted-foreground));
            max-width: 600px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 32px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: var(--transition-smooth);
            font-family: inherit;
            gap: 0.5rem !important;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: hsl(var(--primary-foreground));
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        .btn-outline {
            border: 2px solid hsl(var(--primary-pink));
            color: hsl(var(--primary));
            background: transparent;
        }

        .btn-outline:hover {
            background: hsl(var(--primary));
            color: hsl(var(--primary-foreground));
            transform: translateY(-2px);
        }

        .btn-small {
            padding: 8px 20px;
            font-size: 0.9rem;
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: hsla(var(--background) / 0.95);
            backdrop-filter: blur(10px);
            padding: 16px 0;
            transition: var(--transition-smooth);
            border-bottom: 1px solid hsla(var(--border) / 0.5);
        }

        .header.scrolled {
            background: hsla(var(--background) / 0.98);
            box-shadow: var(--shadow-md);
            padding: 12px 0;
        }

        .header .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-header {
            text-align: center;
            flex: 1;
        }

        .brand-name {
  color: var(--primary-pink);
  font-size: 26px;
  font-weight: 600;
  letter-spacing: 1.5px;
  font-family: "Cormorant Garamond", serif;
  margin-bottom: 2px;
  text-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

        .brand-tagline {
            font-size: 0.75rem;
            color: hsl(var(--muted-foreground));
            letter-spacing: 1px;
            font-weight: 300;
            margin-top: 2px;
        }

        .profile-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-primary);
            border: none;
            color: hsl(var(--primary-foreground));
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition-smooth);
        }

        .profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

    /* Hero Section - Updated for side layout */
.hero {
    height: 100vh;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
}

.hero-background {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.hero-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 1s ease;
}

.hero-slide.active {
    opacity: 1;
}

/* Improved Image Quality */
.hero-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
    image-rendering: high-quality;
    -ms-interpolation-mode: bicubic;
}

/* Hero Content - FIXED SIZING (no transform scale) */
.hero-content {
    position: absolute;
    z-index: 15;
    bottom: 120px; /* Adjusted position */
    left: 60px; /* Adjusted position */
    text-align: left;
    animation: fade-in-up 0.8s ease;

}

.hero-text {
    max-width: 450px; /* Reasonable size */
    position: relative;
}

/* Cleaner Background - less pink */
.hero-text::before {
    content: '';
    position: absolute;
    top: -20px;
    left: -20px;
    right: -20px;
    bottom: -20px;
    background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.15) 0%,
        rgba(255, 255, 255, 0.08) 50%,
        rgba(255, 255, 255, 0.12) 100%
    );
    backdrop-filter: blur(12px);
    border-radius: 16px;
    z-index: -1;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* PERFECT SIZE TITLE - PINK COLOR */
.hero-title {
    font-size: 1.6rem; /* Keeping the perfect smaller size */
    font-weight: 700;
    font-family: 'Playfair Display', serif;
    margin-bottom: 0.8rem;
    color: white; /* Solid pink color */
    line-height: 1.2;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    text-shadow: 
        0 0 20px rgba(255, 105, 180, 0.6),
        0 0 40px rgba(255, 105, 180, 0.4); /* Pink glow only - no dark shadows */
    position: relative;
}

/* CLEAN SUBTITLE */
.hero-subtitle {
    font-size: 1rem;
    margin-bottom: 1.5rem;
    font-weight: 400;
    line-height: 1.5;
    color: #ffffff; /* Solid white */
    text-shadow: 
        0 0 15px rgba(255, 105, 180, 0.5),
        0 0 30px rgba(255, 105, 180, 0.3); /* Pink glow only */
    position: relative;
}

.hero-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    position: relative;
}

/* Clean buttons */
.hero-buttons .btn {
    position: relative;
    z-index: 2;
    padding: 0.6rem 1.2rem;
    font-size: 0.9rem;
}

.hero-buttons .btn-primary {
    background: linear-gradient(135deg, #ff69b4, #ff1493);
    border: none;
    box-shadow: 0 4px 15px rgba(255, 105, 180, 0.4);
}

.hero-buttons .btn-outline {
    border: 2px solid rgba(255, 255, 255, 0.9);
    color: #ff1493;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.hero-buttons .btn-outline:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: #ff1493;
    color: #ff1493;
    box-shadow: 0 4px 20px rgba(255, 255, 255, 0.3);
}

/* FIXED SCROLL INDICATOR POSITIONING */
.scroll-indicator {
    position: absolute;
    bottom: 40px; /* ABOVE slide indicators */
    left: 50%;
    transform: translateX(-50%);
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    animation: bounce 2s infinite;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    z-index: 25; /* HIGHER than slide indicators */
}

.scroll-indicator span {
    font-size: 0.9rem;
    font-weight: 300;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5);
}

/* Slide indicators - BELOW scroll indicator */
.slide-indicators {
    position: absolute;
    bottom: 20px; /* LOWER than scroll indicator */
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
    z-index: 20; /* LOWER than scroll indicator */
}

.slide-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    border: 2px solid rgba(255, 255, 255, 0.3);
    cursor: pointer;
    transition: all 0.3s ease;
}

.slide-indicator.active {
    background: #ffffff;
    border-color: #ffffff;
    transform: scale(1.2);
}

/* Mobile Layout - KEEPING SMALLER SIZES */
@media (max-width: 768px) {
    .hero-content {
        bottom: 100px; /* Adjusted for mobile */
        left: 20px;
        right: 20px;
    }
    
    .hero-text {
        max-width: 100%;
        text-align: center;
    }
    
    .hero-text::before {
        top: -15px;
        left: -10px;
        right: -10px;
        bottom: -15px;
        background: linear-gradient(
            135deg,
            rgba(255, 255, 255, 0.2) 0%,
            rgba(255, 255, 255, 0.1) 50%,
            rgba(255, 255, 255, 0.15) 100%
        );
    }
    
    .hero-title {
        font-size: 1.3rem; /* Keeping smaller mobile size */
       
        text-shadow: 
            0 0 15px rgba(255, 105, 180, 0.7),
            0 0 30px rgba(255, 105, 180, 0.5); /* Pink glow only */
    }
    
    .hero-subtitle {
        font-size: 0.9rem;
        text-shadow: 
            0 0 12px rgba(255, 105, 180, 0.6),
            0 0 25px rgba(255, 105, 180, 0.4); /* Pink glow only */
    }
    
    .hero-buttons {
        justify-content: center;
        gap: 0.8rem;
    }
    
    .hero-buttons .btn {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }
    
    .scroll-indicator {
        bottom: 50px; /* Adjusted for mobile */
    }
    
    .slide-indicators {
        bottom: 25px; /* Adjusted for mobile */
    }
}

/* Desktop Enhancements - KEEPING SMALLER SIZES */
@media (min-width: 769px) {
    .hero-title {
        font-size: 1.8rem; /* Keeping smaller desktop size */
      
        text-shadow: 
            0 0 25px rgba(255, 105, 180, 0.7),
            0 0 50px rgba(255, 105, 180, 0.5); /* Pink glow only */
    }
    
    .hero-content {
        left: 80px; /* More from left on larger screens */
        bottom: 140px;
    }
}

/* Large Desktop - KEEPING SMALLER SIZES */
@media (min-width: 1200px) {
    .hero-content {
        left: 100px;
        bottom: 160px;
    }
    
    .hero-title {
        font-size: 2rem; /* Keeping smaller large desktop size */
    }
}

/* Rest of your existing styles remain the same */
.sparkle-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 2;
}

.sparkle {
    position: absolute;
    width: 6px;
    height: 6px;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 50%;
    animation: sparkle 3s ease-in-out infinite;
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
}

/* Mobile overlay */
@media (max-width: 768px) {
    .hero-slide::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(
            to bottom,
            rgba(0, 0, 0, 0.2) 0%,
            rgba(0, 0, 0, 0.1) 50%,
            rgba(0, 0, 0, 0.3) 100%
        );
    }
}

/* Fix for image quality */
.hero-slide picture {
    display: block;
    width: 100%;
    height: 100%;
}

.hero-slide picture img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

/* Animation keyframes */
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateX(-50%) translateY(0);
    }
    40% {
        transform: translateX(-50%) translateY(-10px);
    }
    60% {
        transform: translateX(-50%) translateY(-5px);
    }
}

@keyframes sparkle {
    0%, 100% {
        opacity: 0;
        transform: scale(0);
    }
    50% {
        opacity: 1;
        transform: scale(1);
    }
}
        /* Store Intro */
        .store-intro {
            padding: 120px 0;
            position: relative;
            overflow: hidden;
        }

        .floating-decor {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .decor-1 {
            top: 80px;
            left: 40px;
            width: 128px;
            height: 128px;
            background: hsl(var(--primary));
        }

        .decor-2 {
            bottom: 80px;
            right: 40px;
            width: 160px;
            height: 160px;
            background: hsl(var(--pink-light));
            animation-delay: 1s;
        }

        .intro-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .intro-content {
            animation: fade-in-up 0.8s ease;
        }

        .intro-text {
            font-size: 1.125rem;
            color: hsl(var(--foreground) / 0.8);
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .intro-text p {
            margin-bottom: 1rem;
        }

        .intro-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .intro-image {
            position: relative;
            animation: slide-in-right 0.8s ease;
        }

        .image-container {
            position: relative;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: var(--transition-smooth);
        }

        .image-container:hover {
            transform: scale(1.02);
        }

        .image-container img {
            width: 100%;
            height: 500px;
            object-fit: cover;
        }

        .image-stats {
            position: absolute;
            bottom: 32px;
            left: 32px;
            right: 32px;
            display: flex;
            gap: 1rem;
        }

        .stat-card {
            flex: 1;
            background: hsla(var(--background) / 0.9);
            backdrop-filter: blur(10px);
            border-radius: calc(var(--radius) - 4px);
            padding: 1.5rem;
            text-align: center;
            transform: translateY(0);
            transition: var(--transition-smooth);
        }

        .image-container:hover .stat-card {
            transform: translateY(-8px);
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            background: var(--gradient-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: hsl(var(--muted-foreground));
        }

        .image-decor {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.2;
            animation: pulse 2s ease-in-out infinite;
        }

        .decor-3 {
            top: -24px;
            right: -24px;
            width: 96px;
            height: 96px;
            background: hsl(var(--gold));
        }

        .decor-4 {
            bottom: -32px;
            left: -32px;
            width: 128px;
            height: 128px;
            background: hsl(var(--primary));
            animation-delay: 1s;
        }
        

/* Store Carousel Styles */
.store-carousel {
    position: relative;
    width: 100%;
    height: 500px;
    overflow: hidden;
    border-radius: 20px;
}

.store-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 1s ease-in-out;
}

.store-slide.active {
    opacity: 1;
}

.store-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

/* Store Carousel Indicators */
.store-carousel-indicators {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
    z-index: 10;
}

.store-carousel-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    border: 2px solid rgba(255, 255, 255, 0.3);
    cursor: pointer;
    transition: all 0.3s ease;
}

.store-carousel-indicator.active {
    background: white;
    border-color: white;
    transform: scale(1.2);
}

  /* Features Section */
.features {
    padding: 80px 0;
    background: linear-gradient(135deg, #fef5f9 0%, #ffffff 100%);
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 50px;
    margin-top: 60px;
}

/* Feature Cards */
.feature-card {
    background: white;
    padding: 40px 25px 30px;
    border-radius: 25px;
    text-align: center;
    box-shadow: 0 15px 50px rgba(255, 105, 180, 0.08);
    transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    border: 2px solid transparent;
    position: relative;
    overflow: visible;
    margin-top: 60px;
}

.feature-card:hover, .feature-card.mobile-active {
    transform: translateY(-10px);
    box-shadow: 0 25px 0px rgba(255, 105, 180, 0.15);
    border-color: var(--pink-light);
}

/* Feature Preview Container */
.feature-preview-container {
    position: relative;
    margin-bottom: 25px;
    height: 100px;
    transition: all 0.5s ease;
}

/* Feature Icon - Moves to side on hover */
.feature-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 35px;
    box-shadow: 0 15px 35px rgba(255, 105, 180, 0.25);
    position: relative;
    z-index: 10;
    transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* Move icon to top-right corner when hovered OR active on mobile */
.feature-card:hover .feature-icon,
.feature-card.mobile-active .feature-icon {
    transform: translate(120px, -30px) scale(0.8); /* Moves right and up */
    box-shadow: 0 15px 35px rgba(255, 105, 180, 0.4);
}

/* ALL PINK GRADIENTS */
.bg-gradient-1, .bg-gradient-2, .bg-gradient-3 {
    background: linear-gradient(135deg, #FF6B9D, #FF4785);
}
.feature-preview {
    position: absolute;
    top: -160px;
    left: 50%;
    transform: translateX(-50%) scale(0.9);
    width: 240px;
    height: 340px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
    z-index: 5;
    pointer-events: none;
    /* REMOVED the heavy drop-shadow */
    filter: drop-shadow(0 8px 20px rgba(255, 105, 180, 0.15)); /* Much lighter shadow */
}

.feature-preview::before {
    content: '';
    position: absolute;
    top: -40px; /* Reduced from -20px */
    left: -10px; /* Reduced from -20px */
    right: -10px;
    bottom: -10px;
    background: linear-gradient(135deg, #FF6B9D, #FF4785);
    border-radius: 30px; /* Slightly smaller */
    opacity: 0.05; /* Reduced from 0.08 - much more subtle */
    z-index: -1;
    filter: blur(8px); /* Reduced from 15px - much softer */
}

.preview-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    border-radius: 20px; /* Slightly smaller border radius */
    border: 4px solid white; /* Slightly thinner border */
    /* REMOVED the heavy box-shadow */
    box-shadow: 0 15px 35px rgba(255, 105, 180, 0.1); /* Much lighter and cleaner */
}

/* Hover States - Preview becomes more visible */
.feature-card:hover .feature-preview,
.feature-card.mobile-active .feature-preview {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) scale(0.9);
    top: -220px;
    z-index: 5;
}

/* Feature Card Content */
.feature-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    margin-bottom: 15px;
    color: var(--dark);
    font-weight: 700;
    transition: all 0.5s ease;
}

.feature-card p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 20px;
    font-size: 0.95rem;
    transition: all 0.5s ease;
}

/* Adjust text position when icon moves */
.feature-card:hover h3,
.feature-card.mobile-active h3,
.feature-card:hover p,
.feature-card.mobile-active p {
    transform: translateY(-10px); /* Lift text slightly when icon moves */
}

/* GLASSMORPHIC BUTTONS */
.btn-outline {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: var(--pink-primary);
    border: 2px solid rgba(255, 107, 157, 0.3);
    padding: 10px 25px;
    border-radius: 20px;
    font-weight: 600;
    transition: all 0.4s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(255, 105, 180, 0.15);
    font-size: 0.9rem;
}

.btn-outline::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, 
        transparent, 
        rgba(255, 255, 255, 0.4), 
        transparent
    );
    transition: left 0.6s ease;
}

.btn-outline:hover::before {
    left: 100%;
}

.btn-outline:hover {
    background: linear-gradient(135deg, #FF6B9D, #FF4785);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(255, 105, 180, 0.4);
    border-color: transparent;
}

.btn-small {
    padding: 8px 20px;
    font-size: 0.85rem;
}

/* Shine effect for buttons */
@keyframes buttonShine {
    0% {
        background-position: -100% 0;
    }
    100% {
        background-position: 200% 0;
    }
}

.btn-outline:hover {
    background-size: 200% 100%;
    animation: buttonShine 1.5s ease-in-out;
}

/* Responsive Design */
@media (max-width: 768px) {
    .features-grid {
        grid-template-columns: 1fr;
        gap: 40px;
    }
    
    .feature-card {
        padding: 35px 20px 25px;
    }
    
    .feature-preview {
        width: 200px;
        height: 280px;
    }
    
    .feature-card:hover .feature-preview,
    .feature-card.mobile-active .feature-preview {
        transform: translateX(-50%) scale(0.9);
        top: -170px;
    }
    
    .feature-preview-container {
        height: 80px;
    }
    
    .feature-icon {
        width: 70px;
        height: 70px;
        font-size: 30px;
    }
    
    /* Smaller movement on mobile */
    .feature-card:hover .feature-icon,
    .feature-card.mobile-active .feature-icon {
        transform: translate(80px, -20px) scale(0.8);
    }
}

    /* ========================================
   TOP PRODUCTS SHOWCASE - PREMIUM DESIGN
   ======================================== */

.products {
    padding: 100px 0;
    position: relative;
    background: linear-gradient(135deg, #fef5f9 0%, #ffffff 50%, #fef5f9 100%);
    overflow: hidden;
}

/* Animated Background Decoration */
.bg-decor {
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255, 105, 180, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(255, 182, 193, 0.06) 0%, transparent 50%);
    pointer-events: none;
}

.bg-decor::before,
.bg-decor::after {
    content: '';
    position: absolute;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 105, 180, 0.1) 0%, transparent 70%);
    animation: floatBubble 20s ease-in-out infinite;
}

.bg-decor::before {
    top: -100px;
    left: -100px;
    animation-delay: 0s;
}

.bg-decor::after {
    bottom: -100px;
    right: -100px;
    animation-delay: 10s;
}

@keyframes floatBubble {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(50px, 50px) scale(1.1); }
}

/* Section Header */
.section-header {
    text-align: center;
    margin-bottom: 60px;
    position: relative;
}

.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 12px;
}

.section-subtitle {
    font-size: 1.1rem;
    color: #666;
}

/* Carousel Container */
.products-carousel {
    position: relative;
    display: flex;
    align-items: center;
    gap: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

/* Glassmorphic Navigation Buttons */
.carousel-btn {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 107, 157, 0.2);
    color: var(--pink-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 8px 25px rgba(255, 105, 180, 0.15);
    flex-shrink: 0;
    z-index: 10;
    position: relative;
    overflow: hidden;
}

.carousel-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent);
    transition: left 0.6s ease;
}

.carousel-btn:hover::before {
    left: 100%;
}

.carousel-btn:hover {
    background: linear-gradient(135deg, #FF6B9D, #FF4785);
    color: white;
    transform: scale(1.15);
    box-shadow: 0 12px 35px rgba(255, 105, 180, 0.35);
    border-color: transparent;
}

.carousel-btn i {
    font-size: 1.2rem;
}

/* Products Grid - Swipeable Container */
.products-grid-wrapper {
    width: 100%;
    overflow: hidden;
    position: relative;
    cursor: grab;
    user-select: none;
}

.products-grid-wrapper:active {
    cursor: grabbing;
}

.products-grid {
    display: flex;
    gap: 25px;
    transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    will-change: transform;
}

/* Premium Product Card */
.popular-product-card {
    background: white;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(255, 105, 180, 0.08);
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 320px;
    max-width: 320px;
    flex-shrink: 0;
    border: 2px solid transparent;
}

.popular-product-card:hover {
    transform: translateY(-12px) scale(1.02);
    box-shadow: 0 20px 60px rgba(255, 105, 180, 0.2);
    border-color: rgba(255, 107, 157, 0.3);
}

/* Product Image Container */
.product-image-container {
    position: relative;
    width: 100%;
    height: 280px;
    overflow: hidden;
    background: linear-gradient(135deg, #fff5f9, #ffe8f0);
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease, filter 0.4s ease;
}

.popular-product-card:hover .product-image {
    transform: scale(1.1);
    filter: brightness(1.05);
}

/* Product Badges */
.popular-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: linear-gradient(135deg, #FF6B9D, #FF4785);
    color: white;
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    z-index: 10;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(255, 105, 180, 0.3);
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.category-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    z-index: 10;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    color: var(--pink-primary);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stock-alert {
    position: absolute;
    bottom: 15px;
    left: 15px;
    background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
    color: white;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    z-index: 10;
}

/* Product Info Section */
.product-info {
    padding: 25px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    gap: 12px;
}

.product-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.15rem;
    font-weight: 600;
    color: var(--dark);
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.8em;
    transition: color 0.3s ease;
}

.popular-product-card:hover .product-name {
    color: var(--pink-primary);
}

.product-price {
    font-size: 1.35rem;
    font-weight: 700;
    background: linear-gradient(135deg, #FF6B9D, #FF4785);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Star Ratings */
.product-ratings {
    display: flex;
    align-items: center;
    gap: 10px;
}

.stars {
    display: flex;
    gap: 3px;
}

.stars i {
    color: #FFD700;
    font-size: 0.9rem;
    filter: drop-shadow(0 2px 4px rgba(255, 215, 0, 0.3));
}

.rating-count {
    font-size: 0.85rem;
    color: #999;
}

/* Color Variants */
.variant-colors {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.color-circle {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid white;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    position: relative;
}

.color-circle:hover {
    transform: scale(1.25);
    box-shadow: 0 4px 12px rgba(255, 105, 180, 0.3);
}

.color-circle.active {
    border-color: var(--pink-primary);
    transform: scale(1.15);
}

.color-circle.active::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
}

.color-circle-more {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: rgba(255, 107, 157, 0.15);
    color: var(--pink-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
}

/* Product Actions - Glassmorphic Buttons */
.product-actions {
    display: flex;
    gap: 10px;
    margin-top: auto;
    padding-top: 15px;
}

.like-btn {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 107, 157, 0.2);
    color: var(--pink-primary);
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.4s ease;
    box-shadow: 0 4px 15px rgba(255, 105, 180, 0.1);
    position: relative;
    overflow: hidden;
}

.like-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent);
    transition: left 0.6s ease;
}

.like-btn:hover::before {
    left: 100%;
}

.like-btn:hover,
.like-btn.liked {
    background: linear-gradient(135deg, #FF6B9D, #FF4785);
    color: white;
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(255, 105, 180, 0.35);
    border-color: transparent;
}

.add-to-cart-btn {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 107, 157, 0.2);
    color: var(--pink-primary);
    border-radius: 25px;
    padding: 12px 24px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.4s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex: 1;
    box-shadow: 0 4px 15px rgba(255, 105, 180, 0.1);
    position: relative;
    overflow: hidden;
}

.add-to-cart-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent);
    transition: left 0.6s ease;
}

.add-to-cart-btn:hover::before {
    left: 100%;
}

.add-to-cart-btn:hover {
    background: linear-gradient(135deg, #FF6B9D, #FF4785);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255, 105, 180, 0.35);
    border-color: transparent;
}

/* Carousel Indicators */
.carousel-indicators {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 50px;
}

.carousel-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255, 107, 157, 0.2);
    border: none;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.carousel-indicator:hover {
    background: rgba(255, 107, 157, 0.4);
    transform: scale(1.3);
}

.carousel-indicator.active {
    background: linear-gradient(135deg, #FF6B9D, #FF4785);
    width: 35px;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(255, 105, 180, 0.3);
}

/* Empty State */
.empty-products {
    text-align: center;
    padding: 80px 20px;
    color: #999;
}

.empty-products i {
    font-size: 4rem;
    margin-bottom: 25px;
    background: linear-gradient(135deg, #FF6B9D, #FF4785);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.empty-products h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    margin-bottom: 12px;
    color: var(--dark);
}

.empty-products p {
    font-size: 1.1rem;
    margin-bottom: 25px;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .popular-product-card {
        min-width: 280px;
        max-width: 280px;
    }
}

@media (max-width: 768px) {
    .products {
        padding: 70px 0;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .products-carousel {
        gap: 12px;
    }
    
    .carousel-btn {
        width: 45px;
        height: 45px;
    }
    
    .popular-product-card {
        /* UPDATED: Dynamic width for mobile to look less "crampy" */
        min-width: 85vw;
        max-width: 85vw;
    }
    
    .product-image-container {
        height: 240px;
    }
}

@media (max-width: 480px) {
    .carousel-btn {
        width: 40px;
        height: 40px;
    }
    
    .carousel-btn i {
        font-size: 1rem;
    }
    
    .popular-product-card {
        /* UPDATED: Dynamic width for mobile to look less "crampy" */
        min-width: 85vw;
        max-width: 85vw;
    }
}

/* ========================================
   CLIENT SPOTLIGHT SECTION
   ======================================== */
   .client-spotlight {
    padding: 100px 0;
    background: linear-gradient(135deg, #fef5f9 0%, #ffffff 100%);
    position: relative;
    z-index: 1; /* Lower z-index so it doesn't cover contact */
}

.spotlight-carousel {
    position: relative;
    margin: 50px 0;
}
/* ========================================
   CONTACT SECTION - VISIT US TODAY
   ======================================== */
   .contact {
    position: relative;
    width: 100%;
    padding: 80px 0; /* Added padding top and bottom */
    overflow: hidden;
    z-index: 2;
    background: linear-gradient(135deg, #fef5f9 0%, #ffffff 100%);
}

.contact .section-header {
    text-align: center;
    margin-bottom: 60px;
}

.contact .section-title {
    font-family: 'Playfair Display', serif;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 15px;
}

.contact .section-subtitle {
    font-size: 1.1rem;
    color: #666;
    max-width: 500px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Contact Content Container */
.contact-content {
    position: relative;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0; /* No gap between map and glass bar */
}

/* Map Wrapper - Smaller and Centered */
.map-wrapper {
    position: relative;
    width: 80%;
    max-width: 1000px;
    height: 400px;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 25px 70px rgba(255, 105, 180, 0.15);
    z-index: 1;
    border: 1px solid rgba(255, 107, 157, 0.1);
}

.map-iframe {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    filter: saturate(0.9) contrast(1.05);
}

/* Glassmorphic Bar - Same width as map */
.contact-glass-bar {
    position: relative;
    width: 80%; /* Same width as map */
    max-width: 1000px; /* Same max-width as map */
    margin-top: -30px; /* Reduced overlap */
    padding: 30px 40px; /* Reduced padding */
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(25px) saturate(180%);
    -webkit-backdrop-filter: blur(25px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(255, 105, 180, 0.15);
    z-index: 10;
    animation: slideUpGlass 0.8s ease-out;
}

@keyframes slideUpGlass {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Contact Info Grid */
.contact-info-grid {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 30px;
    flex-wrap: wrap;
}

/* Individual Info Item */
.contact-info-item {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
    min-width: 200px;
}

/* Icon Circle */
.info-icon {
    width: 55px;
    height: 55px;
    background: linear-gradient(135deg, #FF6B9D, #FF4785);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
    flex-shrink: 0;
    box-shadow: 0 8px 25px rgba(255, 105, 180, 0.4);
    transition: all 0.4s ease;
}

.contact-info-item:hover .info-icon {
    transform: scale(1.1) rotate(10deg);
    box-shadow: 0 12px 35px rgba(255, 105, 180, 0.5);
}

/* Info Content */
.info-content {
    flex: 1;
}

.info-content h4 {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: #2d2d2d;
    margin-bottom: 5px;
    text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
}

.info-content p {
    font-size: 0.95rem;
    color: #444;
    line-height: 1.5;
    margin: 0;
    font-weight: 500;
    text-shadow: 0 1px 2px rgba(255, 255, 255, 0.6);
}

.closed-text {
    color: #FF6B9D;
    font-weight: 600;
}

/* Divider Lines */
.info-divider {
    width: 2px;
    height: 50px;
    background: linear-gradient(
        to bottom,
        transparent,
        rgba(255, 107, 157, 0.3),
        transparent
    );
    flex-shrink: 0;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .contact-info-grid {
        gap: 20px;
    }
    
    .info-divider {
        display: none;
    }
}

@media (max-width: 968px) {
    .contact {
        padding: 60px 0;
    }
    
    .map-wrapper,
    .contact-glass-bar {
        width: 90%;
        max-width: 800px;
    }
    
    .map-wrapper {
        height: 350px;
    }
    
    .contact-glass-bar {
        margin-top: -25px;
        padding: 25px 30px;
    }
    
    .contact-info-grid {
        flex-direction: column;
        align-items: stretch;
        gap: 20px;
    }
    
    .contact-info-item {
        min-width: 100%;
        justify-content: flex-start;
    }
}

@media (max-width: 768px) {
    .contact .section-title {
        font-size: 2rem;
    }
    
    .map-wrapper,
    .contact-glass-bar {
        width: 95%;
    }
    
    .map-wrapper {
        height: 300px;
        border-radius: 20px;
    }
    
    .contact-glass-bar {
        margin-top: -20px;
        padding: 20px 25px;
        border-radius: 15px;
    }
    
    .info-icon {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }
    
    .info-content h4 {
        font-size: 1rem;
    }
    
    .info-content p {
        font-size: 0.9rem;
    }
}

@media (max-width: 480px) {
    .contact {
        padding: 50px 0;
    }
    
    .contact .section-title {
        font-size: 1.8rem;
    }
    
    .map-wrapper {
        height: 250px;
        border-radius: 15px;
    }
    
    .contact-glass-bar {
        margin-top: -15px;
        padding: 18px 20px;
        border-radius: 12px;
    }
    
    .contact-info-item {
        gap: 12px;
    }
    
    .info-icon {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }
}
        /* Footer */
        .footer {
            background: linear-gradient(to bottom, hsl(var(--foreground) / 0.95), hsl(var(--foreground)));
            color: hsl(var(--background));
            padding: 4rem 0;
        }

        .footer-content {
            text-align: center;
        }

        .footer-brand {
            margin-bottom: 2rem;
        }

        .footer-brand h3 {
            font-size: 2rem;
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            margin-bottom: 0.5rem;
        }

        .footer-brand p {
            color: hsla(var(--background) / 0.8);
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .social-link {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: hsla(var(--background) / 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: hsl(var(--background));
            text-decoration: none;
            transition: var(--transition-smooth);
        }

        .social-link:hover {
            background: hsla(var(--background) / 0.2);
            transform: scale(1.1) translateY(-4px);
        }

        .footer-copyright {
            border-top: 1px solid hsla(var(--background) / 0.1);
            padding-top: 2rem;
        }

        .footer-copyright p {
            color: hsla(var(--background) / 0.7);
            font-size: 0.875rem;
        }

        /* Animations */
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
        }

        @keyframes sparkle {
            0%, 100% {
                opacity: 0;
                transform: scale(0) rotate(0deg);
            }
            50% {
                opacity: 1;
                transform: scale(1) rotate(180deg);
            }
        }

        @keyframes fade-in-up {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slide-in-right {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0) translateX(-50%);
            }
            40% {
                transform: translateY(-15px) translateX(-50%);
            }
            60% {
                transform: translateY(-8px) translateX(-50%);
            }
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 0.2;
            }
            50% {
                opacity: 0.3;
            }
        }

        .animate-float {
            animation: float 3s ease-in-out infinite;
        }

        .animate-shimmer {
            background: linear-gradient(90deg, transparent, hsla(var(--primary-foreground) / 0.2), transparent);
            background-size: 1000px 100%;
            animation: shimmer 2s infinite;
        }

        .animate-sparkle {
            animation: sparkle 2s ease-in-out infinite;
        }

        .animate-fade-in-up {
            animation: fade-in-up 0.6s ease-out forwards;
        }

        .animate-slide-in-right {
            animation: slide-in-right 0.6s ease-out forwards;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
          
            
            .section-title {
                font-size: 3rem;
            }
            
            .carousel-btn.prev {
                left: -20px;
            }
            
            .carousel-btn.next {
                right: -20px;
            }
        }

        @media (max-width: 768px) {
          
            
            .hero-subtitle {
                font-size: 1.25rem;
            }
            
            .section-title {
                font-size: 2.5rem;
            }
            
            .intro-grid,
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .carousel-btn {
                display: none;
            }
            
            .hero-buttons,
            .intro-buttons,
            .contact-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 280px;
            }
            
            .brand-name {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
           
            .section-title {
                font-size: 2rem;
            }
            
            .container {
                padding: 0 16px;
            }
            
            .feature-card,
            .product-card {
                padding: 1.5rem;
            }
            
            .image-stats {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

/* Category Badges */
.category-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    color: white;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 600;
    z-index: 10;
    backdrop-filter: blur(10px);
}

.lipstick-badge {
    background: linear-gradient(135deg, #e91e63, #ad1457);
}

.blush-badge {
    background: linear-gradient(135deg, #ff80ab, #f50057);
}

/* Adjust popular badge position when category badge is present */
.product-image-container .popular-badge {
    top: 12px;
    left: 12px;
}

.product-image-container .category-badge + .popular-badge {
    top: 45px; /* Move down if category badge is present */
}


/* Client Spotlight Section */
.client-spotlight {
    padding: 100px 0;
    background: linear-gradient(135deg, #fff9fc 0%, #ffffff 100%);
}

.client-spotlight .section-header {
    text-align: center;
    margin-bottom: 60px;
}

.client-spotlight .section-subtitle {
    max-width: 600px;
    margin: 0 auto;
    font-size: 1.1rem;
    color: #666;
    line-height: 1.6;
}

/* Carousel Container */
.spotlight-carousel {
    position: relative;
    max-width: 1200px;
    margin: 0 auto 40px;
    overflow: hidden;
    padding: 0 50px;
}

.spotlight-track {
    display: flex;
    transition: transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    gap: 25px;
}

.spotlight-item {
    flex: 0 0 calc(33.333% - 17px); /* 3 items per view, accounting for gap */
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(255, 105, 180, 0.1);
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    aspect-ratio: 1/1;
}

.spotlight-item:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(255, 105, 180, 0.2);
}

.spotlight-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.spotlight-item:hover .spotlight-image {
    transform: scale(1.05);
}

/* Carousel Navigation Buttons */
.carousel-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 50px;
    height: 50px;
    background: white;
    border: none;
    border-radius: 50%;
    box-shadow: 0 5px 20px rgba(255, 105, 180, 0.2);
    color: var(--pink-primary);
    font-size: 18px;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
}

.carousel-btn:hover {
    background: var(--pink-primary);
    color: white;
    transform: translateY(-50%) scale(1.1);
    box-shadow: 0 8px 25px rgba(255, 105, 180, 0.3);
}

.carousel-prev {
    left: 0;
}

.carousel-next {
    right: 0;
}

/* Carousel Indicators */
.carousel-indicators {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 40px;
}

.carousel-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 107, 157, 0.3);
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.carousel-indicator.active {
    background: var(--pink-primary);
    transform: scale(1.2);
}

/* Spotlight Footer */
.spotlight-footer {
    text-align: center;
    padding: 40px 20px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    border: 2px solid rgba(255, 107, 157, 0.1);
}

.spotlight-message {
    font-size: 1.1rem;
    color: #555;
    line-height: 1.7;
    margin: 0;
}

.spotlight-message strong {
    color: var(--pink-primary);
    font-weight: 600;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .spotlight-item {
        flex: 0 0 calc(33.333% - 17px);
    }
}

@media (max-width: 768px) {
    .client-spotlight {
        padding: 80px 0;
    }
    
    .spotlight-carousel {
        padding: 0 40px;
    }
    
    .spotlight-item {
        flex: 0 0 calc(50% - 12px); /* 2 items per view on tablet */
    }
    
    .carousel-btn {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }
    
    .spotlight-footer {
        padding: 30px 15px;
    }
    
    .spotlight-message {
        font-size: 1rem;
    }
}

@media (max-width: 480px) {
    .spotlight-carousel {
        padding: 0 35px;
    }
    
    .spotlight-item {
        flex: 0 0 calc(100% - 10px); /* 1 item per view on mobile */
    }
    
    .spotlight-track {
        gap: 15px;
    }
    
    .carousel-btn {
        width: 35px;
        height: 35px;
        font-size: 14px;
    }
}

/* Ensure pink color variable is defined */
:root {
    --pink-primary: #FF6B9D;
}

    </style>
</head>
<body class="home-page">
    <!-- Header -->
    <header class="header" id="mainHeader">
        <div class="container">
            <div class="brand-header">
                <h1 class="brand-name">Blessence</h1>
                <p class="brand-tagline">Beauty & Blessed Online Makeup Store</p>
            </div>
            <button class="profile-btn" onclick="window.location.href='user/html/login.html'">
                <i class="fas fa-user"></i>
            </button>
        </div>
    </header>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-background">
       <!-- Slide 1 -->
       <div class="hero-slide active">
            <picture>
                <source media="(max-width: 768px)" srcset="/uploads/index_images/foundations.webp">
                <source media="(min-width: 769px)" srcset="/uploads/index_images/foundations-desktop.webp">
                <img src="/uploads/index_images/foundations-desktop.webp" alt="Beauty Foundations">
            </picture>
        </div>
        
        <!-- Slide 2 -->
        <div class="hero-slide">
            <picture>
                <source media="(max-width: 768px)" srcset="/uploads/index_images/lipstick.webp">
                <source media="(min-width: 769px)" srcset="/uploads/index_images/lipstick-desktop.webp">
                <img src="/uploads/index_images/lipstick-desktop.webp" alt="Lipsticks Collection">
            </picture>
        </div>
        
        <!-- Slide 3 -->
        <div class="hero-slide">
            <picture>
                <source media="(max-width: 768px)" srcset="/uploads/index_images/skincare.webp">
                <source media="(min-width: 769px)" srcset="/uploads/index_images/skincare-desktop.webp">
                <img src="/uploads/index_images/skincare-desktop.webp" alt="Skincare Products">
            </picture>
        </div>
        
        <!-- Slide 4 -->
        <div class="hero-slide">
            <picture>
                <source media="(max-width: 768px)" srcset="/uploads/index_images/store.webp">
                <source media="(min-width: 769px)" srcset="/uploads/index_images/store-desktop.webp">
                <img src="/uploads/index_images/store-desktop.webp" alt="Beauty & Blessed Store">
            </picture>
        </div>
    </div>

    <!-- Sparkle Effects -->
    <div class="sparkle-container"></div>

    <!-- Hero Content - NOW IN BOTTOM LEFT CORNER -->
    <div class="hero-content">
        <div class="hero-text">
            <h1 class="hero-title">Makeup . Cosmetics . Personal Care</h1>
            <p class="hero-subtitle">Products designed to bring out your natural glow!</p>
            <div class="hero-buttons">
                <button class="btn btn-primary" onclick="window.location.href='user/html/login.html'">Shop Now</button>
                <button class="btn btn-outline" onclick="scrollToSection('features')">Virtual Try-On</button>
            </div>
        </div>
    </div>

    <!-- Scroll Indicator -->
    <button class="scroll-indicator" onclick="scrollToSection('store-intro')">
        <span>Discover More</span>
        <i class="fas fa-chevron-down"></i>
    </button>

    <!-- Slide Indicators (moved up slightly) -->
    <div class="slide-indicators">
        <button class="slide-indicator active" data-slide="0"></button>
        <button class="slide-indicator" data-slide="1"></button>
        <button class="slide-indicator" data-slide="2"></button>
        <button class="slide-indicator" data-slide="3"></button>
    </div>
</section>

<section class="products" id="products">
    <div class="bg-decor"></div>
    <div class="container">
        <!-- Section Header -->
        <div class="section-header">
            <h2 class="section-title">Top Selling Products</h2>
            <p class="section-subtitle">Best-loved by our glowing community ✨</p>
        </div>

        <!-- Products Carousel -->
        <div class="products-carousel">
            <!-- Previous Button -->
            <button class="carousel-btn prev" aria-label="Previous products">
                <i class="fas fa-chevron-left"></i>
            </button>

            <!-- Products Grid (will be wrapped by JavaScript) -->
            <div class="products-grid" id="productsGrid">
                <!-- Products will be dynamically loaded by JavaScript -->
            </div>

            <!-- Next Button -->
            <button class="carousel-btn next" aria-label="Next products">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <!-- Carousel Indicators -->
        <div class="carousel-indicators" id="carouselIndicators">
            <!-- Indicators will be dynamically loaded by JavaScript -->
        </div>
    </div>
</section>


    
<!-- Store Introduction -->
<section class="store-intro" id="store-intro">
    <div class="floating-decor decor-1"></div>
    <div class="floating-decor decor-2"></div>
    
    <div class="container">
        <div class="intro-grid">
            <div class="intro-content">
                <h2 class="section-title">Where Beauty Meets Purpose</h2>
                <div class="intro-text">
                    <p>At <strong>Beauty & Blessed</strong>, we believe true beauty begins with self-care and confidence.</p>
                    <p>From our cosmetic products imported from Malaysia to our rejuvenating treatments, every offering is crafted to enhance your natural glow.</p>
                </div>
                <div class="intro-buttons">
                    <button class="btn btn-primary" onclick="window.location.href='user/html/login.html'">Start Your Transformation</button>
                    <a href="https://www.facebook.com/beautyandblessedbatangas" target="_blank" class="btn btn-outline">
                        <i class="fab fa-facebook-f"></i>
                          Visit Our Facebook
                    </a>
                </div>
            </div>
            <div class="intro-image">
                <div class="image-container">
                    <!-- Store Images Carousel -->
                    <div class="store-carousel" id="storeCarousel">
                        <div class="store-slide active">
                            <img src="/uploads/index_images/store_info1.webp" alt="Beauty & Blessed Store">
                        </div>
                        <div class="store-slide">
                            <img src="/uploads/index_images/store_info2.webp" alt="Store Products">
                        </div>
                        <div class="store-slide">
                            <img src="/uploads/index_images/store_info3.webp" alt="Store Interior">
                        </div>
                        <div class="store-slide">
                            <img src="/uploads/index_images/store_info4.webp" alt="Customer Experience">
                        </div>
                    </div>
                <div class="image-decor decor-3"></div>
                <div class="image-decor decor-4"></div>
            </div>
        </div>
    </div>
</section>
 
    <!-- Features Section -->
<section class="features" id="features">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Amazing Features</h2>
            <p class="section-subtitle">Discover our innovative beauty technology designed just for you</p>
        </div>

        <div class="features-grid">
            <!-- Feature 1: Smart Beauty Quiz -->
            <div class="feature-card">
                <div class="feature-preview-container">
                    <div class="feature-preview">
                        <img src="/uploads/index_images/smart-quiz.webp" 
                             alt="Smart Beauty Quiz Preview" 
                             class="preview-image">
                    </div>
                    <div class="feature-icon bg-gradient-1">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
                <h3>Smart Beauty Quiz</h3>
                <p>Get personalized product recommendations using AI technology tailored to your unique beauty needs</p>
                <button class="btn btn-outline btn-small" onclick="window.location.href='user/html/login.html'">
                    Take Quiz
                </button>
            </div>

            <!-- Feature 2: Virtual Try-On -->
            <div class="feature-card">
                <div class="feature-preview-container">
                    <div class="feature-preview">
                        <img src="/uploads/index_images/try-on.webp" 
                             alt="Virtual Try-On Preview" 
                             class="preview-image">
                    </div>
                    <div class="feature-icon bg-gradient-2">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <h3>Virtual Try-On</h3>
                <p>Try lipsticks and makeup virtually before purchasing. See how products look on you instantly!</p>
                <button class="btn btn-outline btn-small" onclick="window.location.href='user/html/login.html'">
                    Try Now
                </button>
            </div>

            <!-- Feature 3: QR Code Ordering -->
            <div class="feature-card">
                <div class="feature-preview-container">
                    <div class="feature-preview">
                        <img src="/uploads/index_images/qrcode.webp" 
                             alt="QR Code Ordering Preview" 
                             class="preview-image">
                    </div>
                    <div class="feature-icon bg-gradient-3">
                        <i class="fas fa-qrcode"></i>
                    </div>
                </div>
                <h3>QR Code Ordering</h3>
                <p>Order via QR codes for easy scanning when buying from our store. Quick & convenient!</p>
                <button class="btn btn-outline btn-small" onclick="window.location.href='user/html/login.html'">
                    Learn More
                </button>
            </div>
        </div>
    </div>
</section>
   
<!-- Client Spotlight Section -->
<section class="client-spotlight" id="clients">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Client Spotlight</h2>
            <p class="section-subtitle">Thank you to our lovely walk-in clients! ✨ We're so happy you dropped by and trusted Beauty & Blessed for your makeup and skincare needs.</p>
        </div>

        <!-- Carousel Container -->
        <div class="spotlight-carousel">
            <button class="carousel-btn carousel-prev" onclick="prevSpotlight()">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <div class="spotlight-track">
                <!-- All 9 images in the track -->
                <div class="spotlight-item">
                    <img src="/uploads/index_images/client1.webp" alt="Happy Client 1" class="spotlight-image">
                </div>
                <div class="spotlight-item">
                    <img src="/uploads/index_images/client2.webp" alt="Happy Client 2" class="spotlight-image">
                </div>
                <div class="spotlight-item">
                    <img src="/uploads/index_images/client3.webp" alt="Happy Client 3" class="spotlight-image">
                </div>
                <div class="spotlight-item">
                    <img src="/uploads/index_images/client4.webp" alt="Happy Client 4" class="spotlight-image">
                </div>
                <div class="spotlight-item">
                    <img src="/uploads/index_images/client5.webp" alt="Happy Client 5" class="spotlight-image">
                </div>
                <div class="spotlight-item">
                    <img src="/uploads/index_images/client6.webp" alt="Happy Client 6" class="spotlight-image">
                </div>
                <div class="spotlight-item">
                    <img src="/uploads/index_images/client7.webp" alt="Happy Client 7" class="spotlight-image">
                </div>
                <div class="spotlight-item">
                    <img src="/uploads/index_images/client8.webp" alt="Happy Client 8" class="spotlight-image">
                </div>
                <div class="spotlight-item">
                    <img src="/uploads/index_images/client9.webp" alt="Happy Client 9" class="spotlight-image">
                </div>
            </div>
            
            <button class="carousel-btn carousel-next" onclick="nextSpotlight()">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <!-- Carousel Indicators -->
        <div class="carousel-indicators" id="spotlightIndicators">
            <!-- Indicators will be generated by JavaScript -->
        </div>
    </div>
</section>


 <!-- ========================================
     CONTACT SECTION - VISIT US TODAY
     ======================================== -->
<section class="contact" id="contact">
    <div class="container">
        <!-- Section Header -->
        <div class="section-header">
            <h2 class="section-title">Visit Us Today!</h2>
        </div>
    </div>

    <!-- Map and Glass Bar Container -->
    <div class="contact-content">
        <!-- Map -->
        <div class="map-wrapper">
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d243.37688324644633!2d120.63316899999999!3d14.072711700000001!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd97866d03c601%3A0x48535fbc647a2549!2sBeauty%20and%20Blessed!5e0!3m2!1sen!2sph!4v1234567890"
                class="map-iframe"
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>

        <!-- Glassmorphic Info Bar -->
        <div class="contact-glass-bar">
            <div class="container">
                <div class="contact-info-grid">
                    <!-- Location -->
                    <div class="contact-info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-content">
                            <h4>Our Location</h4>
                            <p>Brgy. 4, C. Alvarez Street, Nasugbu, Batangas</p>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="info-divider"></div>

                    <!-- Phone -->
                    <div class="contact-info-item">
                        <div class="info-icon">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div class="info-content">
                            <h4>Call Us</h4>
                            <p>+63 966 944 5591</p>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="info-divider"></div>

                    <!-- Hours -->
                    <div class="contact-info-item">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="info-content">
                            <h4>Store Hours</h4>
                            <p>Mon-Fri & Sun: 9AM-9PM<br><span class="closed-text">Sat: Closed</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                <h3>Blessence</h1>
                <p>Beauty & Blessed Online Makeup Store</p>
                </div>


                <div class="footer-copyright">
                    <p>&copy; 2025 AnalytIQ. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
    <script>
    // Global Variables
    let currentProductIndex = 0;
    const productsPerPage = 4;
    let popularProducts = [];

    let currentIndex = 0;
let isDragging = false;
let startPos = 0;
let startPosY = 0; // Added for vertical scroll check
let currentTranslate = 0;
let prevTranslate = 0;
let animationID = 0;
let currentProductsClone = [];
let currentSpotlightIndex = 0;
let spotlightItemsPerView = 3;
let totalSpotlightSlides = 0;

    document.addEventListener('DOMContentLoaded', function() {            
        initHeroSlider();
        initScrollHeader();
        initSparkles();
        initStoreCarousel();
        initSpotlightCarousel();
        initFeaturesIntersectionObserver(); // Added new observer for mobile features
        
        // Fetch products first, THEN initialize the carousel
        fetchPopularProducts().then(() => {
            initProductsCarousel();
        }).catch(error => {
            console.error('Failed to load products:', error);
            // Initialize carousel with empty state
            initProductsCarousel();
        });
    });

    function initFeaturesIntersectionObserver() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                // MODIFICATION HERE: 
                // If screen is wider than 768px (Desktop), remove the class and do nothing.
                // This forces Desktop to use the CSS :hover state instead.
                if (window.innerWidth > 768) {
                    entry.target.classList.remove('mobile-active');
                    return;
                }

                // Existing logic for Mobile
                if (entry.isIntersecting) {
                    entry.target.classList.add('mobile-active');
                } else {
                    entry.target.classList.remove('mobile-active');
                }
            });
        }, {
            threshold: 0.6 // Trigger when 60% of the card is visible
        });

        const featureCards = document.querySelectorAll('.feature-card');
        featureCards.forEach(card => observer.observe(card));
    }

    function initSpotlightCarousel() {
    updateSpotlightItemsPerView();
    const items = document.querySelectorAll('.spotlight-item');
    totalSpotlightSlides = Math.ceil(items.length / spotlightItemsPerView);
    
    updateSpotlightCarousel();
    createSpotlightIndicators();
    
    // Auto-rotate every 5 seconds
    setInterval(nextSpotlight, 5000);
    
    // Update on window resize
    window.addEventListener('resize', function() {
        updateSpotlightItemsPerView();
        totalSpotlightSlides = Math.ceil(items.length / spotlightItemsPerView);
        updateSpotlightCarousel();
        createSpotlightIndicators();
    });
}

function updateSpotlightItemsPerView() {
    const width = window.innerWidth;
    if (width <= 480) {
        spotlightItemsPerView = 1; // 1 item on mobile
    } else if (width <= 768) {
        spotlightItemsPerView = 2; // 2 items on tablet
    } else {
        spotlightItemsPerView = 3; // 3 items on desktop
    }
}

function updateSpotlightCarousel() {
    const track = document.querySelector('.spotlight-track');
    const itemWidth = document.querySelector('.spotlight-item').offsetWidth + 25; // width + gap
    const translateX = -currentSpotlightIndex * itemWidth * spotlightItemsPerView;
    track.style.transform = `translateX(${translateX}px)`;
    updateSpotlightIndicators();
}

function nextSpotlight() {
    if (currentSpotlightIndex < totalSpotlightSlides - 1) {
        currentSpotlightIndex++;
    } else {
        currentSpotlightIndex = 0; // Loop back to start
    }
    updateSpotlightCarousel();
}

function prevSpotlight() {
    if (currentSpotlightIndex > 0) {
        currentSpotlightIndex--;
    } else {
        currentSpotlightIndex = totalSpotlightSlides - 1; // Loop to end
    }
    updateSpotlightCarousel();
}

function createSpotlightIndicators() {
    const indicatorsContainer = document.getElementById('spotlightIndicators');
    indicatorsContainer.innerHTML = '';
    
    for (let i = 0; i < totalSpotlightSlides; i++) {
        const indicator = document.createElement('button');
        indicator.className = `carousel-indicator ${i === 0 ? 'active' : ''}`;
        indicator.onclick = () => goToSpotlightSlide(i);
        indicatorsContainer.appendChild(indicator);
    }
}

function updateSpotlightIndicators() {
    const indicators = document.querySelectorAll('.carousel-indicator');
    indicators.forEach((indicator, index) => {
        indicator.classList.toggle('active', index === currentSpotlightIndex);
    });
}

function goToSpotlightSlide(index) {
    currentSpotlightIndex = index;
    updateSpotlightCarousel();
}



    // Update fetchPopularProducts with correct path
    async function fetchPopularProducts() {
        try {
            console.log('Fetching popular products...');
            
            // FIX THE PATH - adjust based on your directory structure
            const response = await fetch('user/php/popular-products.php');
            
            // Check if response is OK
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            console.log('Raw response:', text.substring(0, 200));
            
            // Try to parse as JSON
            const data = JSON.parse(text);
            
            if (data.success && data.products && data.products.length > 0) {
                console.log('Products loaded successfully:', data.products.length);
                // Store products globally
                popularProducts = data.products;
                displayPopularProducts(data.products);
                return data.products;
            } else {
                throw new Error('No products in response');
            }
        } catch (error) {
            console.error('Error fetching products:', error);
            // Fallback to hardcoded data
            const fallbackProducts = [
                {
                    id: '1',
                    name: "Malaysian Lipstick",
                    description: "12 bold shades",
                    image: "https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=800&q=80",
                    price: 299,
                    category: 'lipstick',
                    stockQuantity: 10,
                    liked: false,
                    order_count: 0,
                    previewImage: "https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=800&q=80"
                },
                {
                    id: '2',
                    name: "Silk Glow Foundation",
                    description: "Flawless coverage",
                    image: "https://images.unsplash.com/photo-1591370871773-0e21e0d3f14a?w=800&q=80",
                    price: 399,
                    category: 'foundation',
                    stockQuantity: 15,
                    liked: false,
                    order_count: 0,
                    previewImage: "https://images.unsplash.com/photo-1591370871773-0e21e0d3f14a?w=800&q=80"
                },
                {
                    id: '3',
                    name: "Sunset Palette",
                    description: "10 warm tones",
                    image: "https://images.unsplash.com/photo-1627388418467-5c6c51e8c6d1?w=800&q=80",
                    price: 599,
                    category: 'eyeshadow',
                    stockQuantity: 8,
                    liked: false,
                    order_count: 0,
                    previewImage: "https://images.unsplash.com/photo-1627388418467-5c6c51e8c6d1?w=800&q=80"
                },
                {
                    id: '4',
                    name: "Peach Bloom Blush",
                    description: "Buildable color",
                    image: "https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80",
                    price: 249,
                    category: 'blush',
                    stockQuantity: 12,
                    liked: false,
                    order_count: 0,
                    previewImage: "https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80"
                }
            ];
            popularProducts = fallbackProducts;
            displayPopularProducts(fallbackProducts);
            return fallbackProducts;
        }
    }

    function displayPopularProducts(products) {
        const container = document.getElementById('popular-products-container');
        if (!container) {
            console.log('popular-products-container not found');
            return;
        }

        container.innerHTML = products.map(product => `
            <div class="product-card">
                <div class="product-image">
                    <img src="${product.image || product.previewImage || 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=800&q=80'}" 
                         alt="${product.name}" 
                         onerror="this.src='https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=800&q=80'">
                    <button class="wishlist-btn" onclick="toggleWishlist(this, '${product.id}')">
                        <i class="far fa-heart"></i>
                    </button>
                </div>
                <div class="product-info">
                    <h3 class="product-name">${product.name}</h3>
                    <p class="product-category">${product.category}</p>
                    <div class="price">₱${product.price?.toFixed(2) || '0.00'}</div>
                    <button class="add-to-cart" onclick="addToCart('${product.id}')">
                        Add to Cart
                    </button>
                </div>
            </div>
        `).join('');
    }

   // Main initialization function
function initProductsCarousel() {
    console.log('🚀 Initializing products carousel with', popularProducts?.length || 0, 'products');
    
    if (!popularProducts || popularProducts.length === 0) {
        console.warn('No products available for carousel');
        showEmptyState();
        return;
    }
    
    // Create infinite loop by triplicating products
    createInfiniteLoop();
    
    // Wrap grid in swipeable container
    wrapGridForSwiping();
    
    // Render all products
    renderAllProducts();
    
    // Setup drag/swipe functionality
    setupDragAndSwipe();
    
    // Setup navigation buttons
    setupNavigationButtons();
    
    // Update indicators
    updateCarouselIndicators();
    
    // Auto-play carousel (optional)
    // startAutoPlay();
}

// Create infinite loop effect by triplicating products
function createInfiniteLoop() {
    if (popularProducts.length < 4) {
        // If less than 4 products, multiply them to fill carousel
        const repetitions = Math.ceil(12 / popularProducts.length);
        currentProductsClone = Array(repetitions).fill(popularProducts).flat();
    } else {
        // Triplicate for infinite effect: [prev...][current...][next...]
        currentProductsClone = [
            ...popularProducts,
            ...popularProducts,
            ...popularProducts
        ];
    }
    
    // Start at the middle set for seamless looping
    currentIndex = popularProducts.length;
}

// Wrap grid in swipeable container
function wrapGridForSwiping() {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;
    
    const wrapper = document.createElement('div');
    wrapper.className = 'products-grid-wrapper';
    wrapper.id = 'productsGridWrapper';
    
    grid.parentNode.insertBefore(wrapper, grid);
    wrapper.appendChild(grid);
}

// Render all products in the infinite array
function renderAllProducts() {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;
    
    grid.innerHTML = currentProductsClone.map((product, index) => `
        <div class="popular-product-card" 
             data-index="${index}"
             data-aos="fade-up" 
             data-aos-delay="${(index % 4) * 100}">
            ${createProductCardHTML(product)}
        </div>
    `).join('');
    
    // Set initial position to middle set
    setSliderPosition();
}

// Create product card HTML
function createProductCardHTML(product) {
    const cleanProductName = (name) => {
        if (!name) return "";
        return name
            .toString()
            .replace(/Product Record/gi, "")
            .replace(/Parent Record/gi, "")
            .replace(/:\s*/g, "")
            .replace(/\s+/g, " ")
            .trim();
    };

    const displayName = cleanProductName(product.name);
    const LOCAL_FALLBACK_IMAGE = "/admin/uploads/product_images/no-image.png";
    const previewImage = product.previewImage || product.image || LOCAL_FALLBACK_IMAGE;

    const stockAlert = product.stockQuantity && product.stockQuantity <= 5 ? 
        `<div class="stock-alert"> Only ${product.stockQuantity} left!</div>` : "";

    let priceDisplay = "₱0.00";
    const productPrice = parseFloat(product.price);
    if (!isNaN(productPrice) && productPrice > 0) {
        priceDisplay = `₱${productPrice.toFixed(2)}`;
    }

    let variantListHTML = "";
    if (product.variants && product.variants.length > 0) {
        variantListHTML = `
            <div class="variant-colors">
                ${product.variants.slice(0, 5).map((variant, index) => {
                    const hexCode = variant.hexCode || "#CCCCCC";
                    const cleanVariantName = cleanProductName(variant.name);
                    return `
                        <div class="color-circle ${index === 0 ? 'active' : ''}"
                             style="background-color: ${hexCode};"
                             data-variant-id="${variant.id}"
                             title="${cleanVariantName}">
                        </div>
                    `;
                }).join('')}
                ${product.variants.length > 5 ? 
                    `<div class="color-circle-more">+${product.variants.length - 5}</div>` : ''}
            </div>
        `;
    }

    let badgesHTML = '';
    if (product.order_count > 0) {
        badgesHTML += `<div class="popular-badge"> ${product.order_count} sold</div>`;
    }
    
    if (product.category) {
        const categoryLower = product.category.toLowerCase();
        if (categoryLower.includes('lipstick')) {
            badgesHTML += `<div class="category-badge lipstick-badge"> Lipstick</div>`;
        } else if (categoryLower.includes('blush')) {
            badgesHTML += `<div class="category-badge blush-badge"> Blush</div>`;
        }
    }

    return `
        <div class="product-image-container">
            ${badgesHTML}
            ${stockAlert}
            <img src="${previewImage}" 
                 alt="${displayName}"
                 class="product-image"
                 onerror="this.onerror=null; this.src='${LOCAL_FALLBACK_IMAGE}';"
                 draggable="false">
        </div>
        <div class="product-info">
            <h3 class="product-name">${displayName}</h3>
            <div class="product-price">${priceDisplay}</div>
            <div class="product-ratings">
                <div class="stars">
                    ${'<i class="fas fa-star"></i>'.repeat(5)}
                </div>
                <span class="rating-count">${product.order_count ? `(${product.order_count} sold)` : '(New)'}</span>
            </div>
            ${variantListHTML}
            <div class="product-actions">
                <button class="like-btn" 
                        onclick="event.stopPropagation(); window.location.href='user/html/login.html'">
                    <i class="far fa-heart"></i>
                </button>
                <button class="add-to-cart-btn" 
                        onclick="event.stopPropagation(); window.location.href='user/html/login.html'">
                    <i class="fas fa-shopping-cart"></i>View Details
                </button>
            </div>
        </div>
    `;
}

// Setup drag and swipe functionality
function setupDragAndSwipe() {
    const wrapper = document.getElementById('productsGridWrapper');
    const grid = document.getElementById('productsGrid');
    
    if (!wrapper || !grid) return;
    
    // Mouse events
    wrapper.addEventListener('mousedown', dragStart);
    wrapper.addEventListener('mousemove', drag);
    wrapper.addEventListener('mouseup', dragEnd);
    wrapper.addEventListener('mouseleave', dragEnd);
    
    // Touch events
    wrapper.addEventListener('touchstart', dragStart, { passive: true });
    wrapper.addEventListener('touchmove', drag, { passive: false });
    wrapper.addEventListener('touchend', dragEnd);
    
    // Prevent context menu on long press
    wrapper.addEventListener('contextmenu', e => e.preventDefault());
}

function dragStart(event) {
    isDragging = true;
    startPos = getPositionX(event);
    // Capture Y to detect vertical scrolling intention
    startPosY = getPositionY(event);
    
    animationID = requestAnimationFrame(animation);
    
    const wrapper = document.getElementById('productsGridWrapper');
    wrapper.style.cursor = 'grabbing';
}

function drag(event) {
    if (!isDragging) return;
    
    const currentPosition = getPositionX(event);
    const currentPositionY = getPositionY(event);
    
    // UPDATED: Allow vertical scrolling to happen naturally
    // If movement is more vertical than horizontal, do not lock.
    const xDiff = Math.abs(currentPosition - startPos);
    const yDiff = Math.abs(currentPositionY - startPosY);

    if (event.type === 'touchmove') {
        // If swiping horizontally, prevent default to stop page scroll
        if (xDiff > yDiff) {
            event.preventDefault();
        } else {
            // If scrolling vertically, return early so page scrolls
            return;
        }
    }
    
    currentTranslate = prevTranslate + currentPosition - startPos;
}

function dragEnd() {
    isDragging = false;
    cancelAnimationFrame(animationID);
    
    const wrapper = document.getElementById('productsGridWrapper');
    wrapper.style.cursor = 'grab';
    
    // Calculate how far we've moved
    const movedBy = currentTranslate - prevTranslate;
    
    // Determine if we should move to next/prev slide
    // UPDATED: Use dynamic card width for calculation
    const cardWidth = getCardWidth();
    
    // If moved more than 20% of card width
    if (movedBy < -cardWidth * 0.2) {
        // Swiped left - go to next
        currentIndex++;
    } else if (movedBy > cardWidth * 0.2) {
        // Swiped right - go to previous
        currentIndex--;
    }
    
    // Handle infinite loop
    handleInfiniteLoop();
    
    setSliderPosition();
}

// Helper to get card width dynamically (essential for mobile responsiveness)
function getCardWidth() {
    const card = document.querySelector('.popular-product-card');
    if (!card) return 345; // Default fallback
    
    // Get the computed style of the grid to find the gap
    const grid = document.getElementById('productsGrid');
    const style = window.getComputedStyle(grid);
    const gap = parseFloat(style.gap) || 25;
    
    return card.offsetWidth + gap;
}

function getPositionX(event) {
    return event.type.includes('mouse') ? event.pageX : event.touches[0].clientX;
}

function getPositionY(event) {
    return event.type.includes('mouse') ? event.pageY : event.touches[0].clientY;
}

function animation() {
    setSliderPosition();
    if (isDragging) requestAnimationFrame(animation);
}

function setSliderPosition() {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;
    
    // UPDATED: Calculate exact width dynamically
    const cardWidth = getCardWidth();
    currentTranslate = currentIndex * -cardWidth;
    prevTranslate = currentTranslate;
    
    grid.style.transform = `translateX(${currentTranslate}px)`;
}

// Handle infinite loop seamlessly
function handleInfiniteLoop() {
    const totalProducts = popularProducts.length;
    
    // If we're past the last set, jump to first set
    if (currentIndex >= totalProducts * 2) {
        currentIndex = totalProducts;
        const grid = document.getElementById('productsGrid');
        grid.style.transition = 'none';
        setSliderPosition();
        setTimeout(() => {
            grid.style.transition = 'transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
        }, 50);
    }
    
    // If we're before the first set, jump to last set
    if (currentIndex < totalProducts) {
        currentIndex = totalProducts * 2 - 1;
        const grid = document.getElementById('productsGrid');
        grid.style.transition = 'none';
        setSliderPosition();
        setTimeout(() => {
            grid.style.transition = 'transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
        }, 50);
    }
    
    updateCarouselIndicators();
}

// Setup navigation buttons
function setupNavigationButtons() {
    const prevBtn = document.querySelector('.carousel-btn.prev');
    const nextBtn = document.querySelector('.carousel-btn.next');
    
    if (prevBtn) {
        prevBtn.onclick = showPreviousProducts;
    }
    if (nextBtn) {
        nextBtn.onclick = showNextProducts;
    }
}

function showNextProducts() {
    currentIndex++;
    handleInfiniteLoop();
    setSliderPosition();
}

function showPreviousProducts() {
    currentIndex--;
    handleInfiniteLoop();
    setSliderPosition();
}

// Update carousel indicators
function updateCarouselIndicators() {
    const indicators = document.getElementById('carouselIndicators');
    if (!indicators || !popularProducts) return;
    
    const totalProducts = popularProducts.length;
    const actualIndex = ((currentIndex % totalProducts) + totalProducts) % totalProducts;
    
    indicators.innerHTML = Array.from({ length: totalProducts }, (_, index) => `
        <button class="carousel-indicator ${actualIndex === index ? 'active' : ''}" 
                onclick="goToProductPage(${index})"></button>
    `).join('');
}

function goToProductPage(pageIndex) {
    const totalProducts = popularProducts.length;
    currentIndex = totalProducts + pageIndex; // Jump to middle set
    setSliderPosition();
    updateCarouselIndicators();
}

// Auto-play functionality (optional)
let autoPlayInterval;

function startAutoPlay(delay = 4000) {
    stopAutoPlay(); // Clear any existing interval
    autoPlayInterval = setInterval(() => {
        showNextProducts();
    }, delay);
}

function stopAutoPlay() {
    if (autoPlayInterval) {
        clearInterval(autoPlayInterval);
    }
}

// Pause auto-play on hover
const wrapper = document.getElementById('productsGridWrapper');
if (wrapper) {
    wrapper.addEventListener('mouseenter', stopAutoPlay);
    wrapper.addEventListener('mouseleave', () => startAutoPlay(4000));
}

// Empty state
function showEmptyState() {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;
    
    grid.innerHTML = `
        <div class="empty-products">
            <i class="fas fa-gift"></i>
            <h3>No Products Available</h3>
            <p>Check back soon for our amazing products!</p>
            <button class="btn btn-outline btn-small" onclick="window.location.href='user/html/login.html'">
                Explore More
            </button>
        </div>
    `;
}

// Fallback functions for compatibility
function toggleWishlist(button, productId) {
    window.location.href = 'user/html/login.html';
}

function addToCart(productId) {
    window.location.href = 'user/html/login.html';
}

function toggleLike(productId) {
    window.location.href = 'user/html/login.html';
}

   // Simple Store Carousel
function initStoreCarousel() {
    console.log('🚀 initStoreCarousel started');
    const carousel = document.getElementById('storeCarousel');
    if (!carousel) {
        console.error('❌ Store carousel element not found!');
        return;
    }
    
    const slides = carousel.querySelectorAll('.store-slide');
    console.log(`Found ${slides.length} slides`);
    
    if (slides.length <= 1) {
        console.log('Only one slide found, no rotation needed');
        return;
    }
    
    let currentSlide = 0;
    
    function showSlide(index) {
        // Remove active class from all slides
        slides.forEach(slide => slide.classList.remove('active'));
        
        // Add active class to current slide
        slides[index].classList.add('active');
        currentSlide = index;
        
        console.log(`Showing slide ${index + 1} of ${slides.length}`);
    }
    
    function nextSlide() {
        let nextIndex = (currentSlide + 1) % slides.length;
        showSlide(nextIndex);
    }
    
    // Auto-rotate slides every 4 seconds
    const rotationInterval = setInterval(nextSlide, 4000);
    
    // Optional: Pause on hover
    carousel.addEventListener('mouseenter', () => {
        clearInterval(rotationInterval);
    });
    
    carousel.addEventListener('mouseleave', () => {
        // Restart rotation when mouse leaves
        setInterval(nextSlide, 4000);
    });
    
    // Initialize first slide
    showSlide(0);
    console.log('Store carousel initialized successfully');
}

    // Hero Slider
    function initHeroSlider() {
        const slides = document.querySelectorAll('.hero-slide');
        const indicators = document.querySelectorAll('.slide-indicator');
        
        if (slides.length === 0 || indicators.length === 0) {
            console.log('No slides or indicators found');
            return;
        }
        
        let currentSlide = 0;

        function showSlide(index) {
            if (index < 0 || index >= slides.length) return;
            
            slides.forEach(slide => slide.classList.remove('active'));
            indicators.forEach(indicator => indicator.classList.remove('active'));
            
            slides[index].classList.add('active');
            indicators[index].classList.add('active');
            currentSlide = index;
        }

        setInterval(() => {
            let nextSlide = (currentSlide + 1) % slides.length;
            showSlide(nextSlide);
        }, 5000);

        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => showSlide(index));
        });
        
        showSlide(0);
    }

    // Scroll Header Effect
    function initScrollHeader() {
        const header = document.getElementById('mainHeader');
        
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    // Sparkle Effects
    function initSparkles() {
        const container = document.querySelector('.sparkle-container');
        
        function createSparkle() {
            const sparkle = document.createElement('div');
            sparkle.className = 'sparkle';
            sparkle.style.left = Math.random() * 100 + '%';
            sparkle.style.top = Math.random() * 100 + '%';
            sparkle.style.animationDelay = Math.random() * 2 + 's';
            sparkle.style.animationDuration = (2 + Math.random() * 2) + 's';
            
            container.appendChild(sparkle);
            
            setTimeout(() => {
                sparkle.remove();
            }, 3000);
        }
        
        for (let i = 0; i < 15; i++) {
            setTimeout(createSparkle, i * 200);
        }
        
        setInterval(createSparkle, 300);
    }

    // Utility Functions
    function scrollToSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (section) {
            section.scrollIntoView({ behavior: 'smooth' });
        }
    }

    // Add smooth scrolling for all anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
</script>
</body>
</html>