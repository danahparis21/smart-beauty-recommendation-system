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
    <title>Beauty & Blessed - Premium Makeup & Beauty Products</title>
    <meta name="description" content="Invest in yourself with Beauty & Blessed. Discover premium Malaysian cosmetics, virtual try-on technology, and personalized beauty recommendations in Nasugbu, Batangas.">
    <meta name="author" content="Beauty & Blessed">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Open Graph -->
    <meta property="og:title" content="Beauty & Blessed - Premium Makeup & Beauty Products">
    <meta property="og:description" content="Invest in yourself — reveal the beauty that's already within. Shop premium Malaysian cosmetics and beauty products.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://lovable.dev/opengraph-image-p98pqg.png">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@Lovable">
    <meta name="twitter:image" content="https://lovable.dev/opengraph-image-p98pqg.png">

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

        /* Features */
        .features {
            padding: 120px 0;
            background: var(--gradient-soft);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: hsla(var(--card) / 0.8);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
            border: 1px solid hsla(var(--border) / 0.5);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, hsla(var(--primary-foreground) / 0.1), transparent);
            transition: left 0.5s ease;
        }

        .feature-card:hover::before {
            left: 100%;
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: calc(var(--radius) - 4px);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: hsl(var(--primary-foreground));
            font-size: 2rem;
            transition: var(--transition-smooth);
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(6deg);
        }

        .bg-gradient-1 {
            background: var(--gradient-primary);
        }

        .bg-gradient-2 {
            background: linear-gradient(135deg, hsl(270 81% 60%), hsl(var(--primary)));
        }

        .bg-gradient-3 {
            background: var(--gradient-gold);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: hsl(var(--foreground));
        }

        .feature-card p {
            color: hsl(var(--muted-foreground));
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

       /* Products Section - Beautiful & Organized */
.products {
    padding: 100px 0;
    position: relative;
    background: var(--gradient-soft);
}

.bg-decor {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, 
        rgba(255, 105, 180, 0.05) 0%, 
        rgba(255, 182, 193, 0.03) 50%, 
        transparent 100%);
}

.products-carousel {
    position: relative;
    display: flex;
    align-items: center;
    gap: 20px;
}

.carousel-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: white;
    border: 2px solid hsl(var(--border));
    color: hsl(var(--primary));
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-md);
    flex-shrink: 0;
}

.carousel-btn:hover {
    background: hsl(var(--primary));
    color: white;
    transform: scale(1.1);
    box-shadow: var(--shadow-glow);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    width: 100%;
}

/* Popular Product Card - Enhanced Styling */
.popular-product-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all 0.4s ease;
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.popular-product-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
}

.popular-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
    color: white;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    z-index: 10;
    backdrop-filter: blur(10px);
}

.product-image-container {
    position: relative;
    width: 100%;
    height: 250px;
    overflow: hidden;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.popular-product-card:hover .product-image {
    transform: scale(1.08);
}

.product-info {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    gap: 12px;
}

.product-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: hsl(var(--foreground));
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.8em;
    font-family: 'Playfair Display', serif;
}

.product-price {
    font-size: 1.25rem;
    font-weight: 700;
    color: hsl(var(--primary));
}

.product-ratings {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stars {
    display: flex;
    gap: 2px;
}

.stars i {
    color: #ffd700;
    font-size: 0.8rem;
}

.rating-count {
    font-size: 0.8rem;
    color: hsl(var(--muted-foreground));
}

.variant-colors {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.color-circle {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid white;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.color-circle:hover {
    transform: scale(1.2);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.color-circle.active {
    border-color: hsl(var(--primary));
    transform: scale(1.1);
}

.color-circle-more {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: hsl(var(--muted));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
}

.product-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
    padding-top: 15px;
    border-top: 1px solid hsl(var(--border));
}

.like-btn {
    background: none;
    border: none;
    color: hsl(var(--muted-foreground));
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 8px;
}

.like-btn:hover,
.like-btn.liked {
    color: hsl(var(--primary));
    transform: scale(1.1);
}

.add-to-cart-btn {
    background: hsl(var(--primary));
    color: white;
    border: none;
    border-radius: 25px;
    padding: 10px 20px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.add-to-cart-btn:hover {
    background: hsl(var(--primary) / 0.9);
    transform: translateY(-2px);
    box-shadow: var(--shadow-glow);
}

.carousel-indicators {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 40px;
}

.carousel-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: hsl(var(--muted));
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.carousel-indicator.active {
    background: hsl(var(--primary));
    width: 30px;
    border-radius: 5px;
}

.empty-products {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: hsl(var(--muted-foreground));
}

.empty-products i {
    font-size: 3rem;
    margin-bottom: 20px;
    color: hsl(var(--primary));
}

.empty-products h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: hsl(var(--foreground));
}

/* Responsive Design */
@media (max-width: 1200px) {
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .products-carousel {
        gap: 10px;
    }
    
    .carousel-btn {
        width: 40px;
        height: 40px;
    }
    
    .product-image-container {
        height: 200px;
    }
}

@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .carousel-btn {
        display: none;
    }
}

        /* Contact */
        .contact {
            padding: 120px 0;
            background: var(--gradient-soft);
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .contact-info {
            animation: fade-in-up 0.8s ease;
        }

        .contact-items {
            margin: 2rem 0;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 2rem;
            transition: var(--transition-smooth);
        }

        .contact-item:hover {
            transform: translateX(8px);
        }

        .contact-icon {
            width: 48px;
            height: 48px;
            border-radius: calc(var(--radius) - 4px);
            display: flex;
            align-items: center;
            justify-content: center;
            color: hsl(var(--primary-foreground));
            flex-shrink: 0;
            transition: var(--transition-smooth);
        }

        .contact-item:hover .contact-icon {
            transform: scale(1.1) rotate(6deg);
        }

        .contact-item h3 {
            font-weight: 600;
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
            color: hsl(var(--foreground));
        }

        .contact-item p {
            color: hsl(var(--muted-foreground));
            line-height: 1.5;
        }

        .contact-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .contact-image {
            position: relative;
            animation: slide-in-right 0.8s ease;
        }

        .contact-image .image-container {
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .floating-badge {
            position: absolute;
            top: 32px;
            left: 32px;
            background: hsla(var(--background) / 0.95);
            backdrop-filter: blur(10px);
            border-radius: calc(var(--radius) - 4px);
            padding: 1rem;
            box-shadow: var(--shadow-md);
        }

        .badge-label {
            font-size: 0.875rem;
            color: hsl(var(--muted-foreground));
            margin-bottom: 0.25rem;
        }

        .badge-title {
            font-size: 1.5rem;
            font-weight: 700;
            background: var(--gradient-text);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .contact-image .image-decor {
            bottom: -32px;
            right: -32px;
            width: 160px;
            height: 160px;
            background: hsl(var(--gold));
            opacity: 0.3;
            filter: blur(48px);
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

    </style>
</head>
<body class="home-page">
    <!-- Header -->
    <header class="header" id="mainHeader">
        <div class="container">
            <div class="brand-header">
                <h1 class="brand-name">Beauty & Blessed</h1>
                <p class="brand-tagline">Elevate Your Everyday Glam</p>
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
                <source media="(max-width: 768px)" srcset="/uploads/index_images/foundations.jpg">
                <source media="(min-width: 769px)" srcset="/uploads/index_images/foundations-desktop.png">
                <img src="/uploads/index_images/foundations-desktop.png" alt="Beauty Foundations">
            </picture>
        </div>
        
        <!-- Slide 2 -->
        <div class="hero-slide">
            <picture>
                <source media="(max-width: 768px)" srcset="/uploads/index_images/lipstick.jpg">
                <source media="(min-width: 769px)" srcset="/uploads/index_images/lipstick-desktop.png">
                <img src="/uploads/index_images/lipstick-desktop.png" alt="Lipsticks Collection">
            </picture>
        </div>
        
        <!-- Slide 3 -->
        <div class="hero-slide">
            <picture>
                <source media="(max-width: 768px)" srcset="/uploads/index_images/skincare.jpg">
                <source media="(min-width: 769px)" srcset="/uploads/index_images/skincare-desktop.png">
                <img src="/uploads/index_images/skincare-desktop.png" alt="Skincare Products">
            </picture>
        </div>
        
        <!-- Slide 4 -->
        <div class="hero-slide">
            <picture>
                <source media="(max-width: 768px)" srcset="/uploads/index_images/store.jpg">
                <source media="(min-width: 769px)" srcset="/uploads/index_images/store-desktop.png">
                <img src="/uploads/index_images/store-desktop.png" alt="Beauty & Blessed Store">
            </picture>
        </div>
    </div>

    <!-- Sparkle Effects -->
    <div class="sparkle-container"></div>

    <!-- Hero Content - NOW IN BOTTOM LEFT CORNER -->
    <div class="hero-content">
        <div class="hero-text">
            <h1 class="hero-title">Makeup . Cosmetics . Personal Care</h1>
            <p class="hero-subtitle">Invest in yourself — reveal the beauty that's already within</p>
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

<!-- Products -->
<section class="products">
        <div class="bg-decor"></div>
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Top Selling Products</h2>
                <p class="section-subtitle">Best-loved by our glowing community</p>
            </div>

            <div class="products-carousel">
                <button class="carousel-btn prev" onclick="showPreviousProducts()">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <div class="products-grid" id="productsGrid">
                    <!-- Products will be loaded by JavaScript -->
                </div>

                <button class="carousel-btn next" onclick="showNextProducts()">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <div class="carousel-indicators" id="carouselIndicators">
                <!-- Indicators will be loaded by JavaScript -->
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
                            <img src="/uploads/index_images/store_info1.jpg" alt="Beauty & Blessed Store">
                        </div>
                        <div class="store-slide">
                            <img src="/uploads/index_images/store_info2.jpg" alt="Store Products">
                        </div>
                        <div class="store-slide">
                            <img src="/uploads/index_images/store_info3.jpg" alt="Store Interior">
                        </div>
                        <div class="store-slide">
                            <img src="/uploads/index_images/store_info4.jpg" alt="Customer Experience">
                        </div>
                    </div>
                <div class="image-decor decor-3"></div>
                <div class="image-decor decor-4"></div>
            </div>
        </div>
    </div>
</section>
 
    <!-- Features -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Amazing Features</h2>
                <p class="section-subtitle">Discover our innovative beauty technology designed just for you</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon bg-gradient-1">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>Smart Beauty Quiz</h3>
                    <p>Get personalized product recommendations using AI technology</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon bg-gradient-2">
                        <i class="fas fa-camera"></i>
                    </div>
                    <h3>Virtual Try-On</h3>
                    <p>Try lipsticks and makeup virtually before purchasing</p>
                    <button class="btn btn-outline btn-small">Try Now</button>
                </div>

                <div class="feature-card">
                    <div class="feature-icon bg-gradient-3">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <h3>QR Code Scanning</h3>
                    <p>Scan in-store QR codes for quick product information</p>
                </div>
            </div>
        </div>
    </section>

   

    <!-- Contact -->
    <section class="contact">
        <div class="container">
            <div class="contact-grid">
                <div class="contact-info">
                    <h2 class="section-title">Visit Us Today</h2>
                    
                    <div class="contact-items">
                        <div class="contact-item">
                            <div class="contact-icon bg-gradient-1">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h3>Location</h3>
                                <p>
                                    Brgy. 4, C. Alvarez Street<br>
                                    Nasugbu, Batangas
                                </p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <div class="contact-icon bg-gradient-3">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <h3>Contact</h3>
                                <p>+63 966 944 5591</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <div class="contact-icon bg-gradient-1">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h3>Hours</h3>
                                <p>
                                    9:00 AM – 9:00 PM<br>
                                    Closed on Saturdays
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="contact-buttons">
                        <button class="btn btn-primary" onclick="window.location.href='user/html/signup.html'">Order Now</button>
                        <button class="btn btn-outline">Virtual Try-On</button>
                    </div>
                </div>

                <div class="contact-image">
                    <div class="image-container">
                        <img src="https://images.unsplash.com/photo-1561715276-a2d087060f1d?w=800&q=80" alt="Store Location">
                        <div class="floating-badge">
                            <p class="badge-label">Open Now</p>
                            <p class="badge-title">Visit Us!</p>
                        </div>
                    </div>
                    <div class="image-decor"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3>Beauty & Blessed</h3>
                    <p>Invest in yourself — reveal the beauty within</p>
                </div>

                <div class="social-links">
                    <a href="#" class="social-link" aria-label="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-link" aria-label="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="social-link" aria-label="TikTok">
                        <i class="fab fa-tiktok"></i>
                    </a>
                </div>

                <div class="footer-copyright">
                    <p>&copy; 2024 Beauty & Blessed. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
    <script>
    // Global Variables
    let currentProductIndex = 0;
    const productsPerPage = 4;
    let popularProducts = [];

    document.addEventListener('DOMContentLoaded', function() {            
        initHeroSlider();
        initScrollHeader();
        initSparkles();
        initStoreCarousel();
        
        // Fetch products first, THEN initialize the carousel
        fetchPopularProducts().then(() => {
            initProductsCarousel();
        }).catch(error => {
            console.error('Failed to load products:', error);
            // Initialize carousel with empty state
            initProductsCarousel();
        });
    });

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

    // SINGLE initProductsCarousel function
    function initProductsCarousel() {
        console.log('Initializing carousel with products:', popularProducts?.length || 0);
        
        // Check if we have products
        if (!popularProducts || popularProducts.length === 0) {
            console.warn('No products available for carousel');
            showEmptyState();
            return;
        }
        
        const grid = document.getElementById('productsGrid');
        if (!grid) {
            console.error('productsGrid element not found');
            return;
        }
        
        renderProducts();
        updateCarouselIndicators();
        
        // Add event listeners for carousel navigation
        const prevBtn = document.querySelector('.carousel-prev');
        const nextBtn = document.querySelector('.carousel-next');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', showPreviousProducts);
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', showNextProducts);
        }
    }

    // SINGLE renderProducts function
    function renderProducts() {
        const grid = document.getElementById('productsGrid');
        if (!grid) return;
        
        const visibleProducts = popularProducts.slice(currentProductIndex, currentProductIndex + productsPerPage);
        
        grid.innerHTML = visibleProducts.map((product, index) => `
            <div class="popular-product-card" data-aos="fade-up" data-aos-delay="${index * 100}">
                ${createProductCardHTML(product)}
            </div>
        `).join('');
    }

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
        const defaultImage = product.image || previewImage || LOCAL_FALLBACK_IMAGE;

        const stockAlert = product.stockQuantity && product.stockQuantity <= 5 ? 
            `<div class="stock-alert">Only ${product.stockQuantity} left!</div>` : "";

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
                                 data-variant-image="${variant.image || LOCAL_FALLBACK_IMAGE}"
                                 title="${cleanVariantName}">
                            </div>
                        `;
                    }).join('')}
                    ${product.variants.length > 5 ? `<div class="color-circle-more">+${product.variants.length - 5}</div>` : ''}
                </div>
            `;
        }

        let badgesHTML = '';
        
        if (product.order_count > 0) {
            badgesHTML += `<div class="popular-badge">🔥 ${product.order_count} sold</div>`;
        }
        
        if (product.category) {
            const categoryLower = product.category.toLowerCase();
            if (categoryLower.includes('lipstick')) {
                badgesHTML += `<div class="category-badge lipstick-badge">💄 Lipstick</div>`;
            } else if (categoryLower.includes('blush')) {
                badgesHTML += `<div class="category-badge blush-badge">🌸 Blush</div>`;
            }
        }

        return `
            <div class="product-image-container">
                ${badgesHTML}
                ${stockAlert}
                <img src="${previewImage}" 
                     alt="${displayName}"
                     class="product-image"
                     data-default="${defaultImage}"
                     data-preview="${previewImage}"
                     onerror="this.onerror=null; this.src='${LOCAL_FALLBACK_IMAGE}';">
            </div>
            <div class="product-info">
                <h3 class="product-name">${displayName}</h3>
                <div class="product-price">${priceDisplay}</div>
                <div class="product-ratings">
                    <div class="stars">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <span class="rating-count">${product.order_count ? `(${product.order_count} sold)` : '(New)'}</span>
                </div>
                ${variantListHTML}
                <div class="product-actions">
                    <button class="like-btn ${product.liked ? 'liked' : ''}" 
                            onclick="event.stopPropagation(); toggleLike('${product.id}')">
                        <i class="${product.liked ? 'fas' : 'far'} fa-heart"></i>
                    </button>
                    <button class="add-to-cart-btn" 
                            onclick="event.stopPropagation(); window.location.href='productdetail.html?productId=${product.id}'">
                        <i class="fas fa-shopping-cart"></i>Add to Cart
                    </button>
                </div>
            </div>
        `;
    }

    function showNextProducts() {
        if (currentProductIndex + productsPerPage < popularProducts.length) {
            currentProductIndex += productsPerPage;
            renderProducts();
            updateCarouselIndicators();
        }
    }

    function showPreviousProducts() {
        if (currentProductIndex - productsPerPage >= 0) {
            currentProductIndex -= productsPerPage;
            renderProducts();
            updateCarouselIndicators();
        }
    }

    function updateCarouselIndicators() {
        const indicators = document.getElementById('carouselIndicators');
        if (!indicators) return;
        
        const totalPages = Math.ceil(popularProducts.length / productsPerPage);
        
        indicators.innerHTML = Array.from({ length: totalPages }, (_, index) => `
            <button class="carousel-indicator ${Math.floor(currentProductIndex / productsPerPage) === index ? 'active' : ''}" 
                    onclick="goToProductPage(${index})"></button>
        `).join('');
    }

    function goToProductPage(pageIndex) {
        currentProductIndex = pageIndex * productsPerPage;
        renderProducts();
        updateCarouselIndicators();
    }

    function showEmptyState() {
        const grid = document.getElementById('productsGrid');
        if (!grid) return;
        
        grid.innerHTML = `
            <div class="empty-products">
                <i class="fas fa-gift"></i>
                <h3>No Popular Products Yet</h3>
                <p>Be the first to discover our amazing products!</p>
                <button class="btn btn-primary" onclick="window.location.href='user/html/home.html'">
                    Explore All Products
                </button>
            </div>
        `;
    }

    function toggleWishlist(button, productId) {
        console.log('Toggle wishlist for product:', productId);
    }

    function addToCart(productId) {
        console.log('Add to cart:', productId);
    }

    function toggleLike(productId) {
        console.log('Toggle like for product:', productId);
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
        if (slides.length <= 1) {
            return;
        }
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