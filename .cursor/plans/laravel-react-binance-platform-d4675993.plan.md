<!-- d4675993-525e-4995-8b72-5c6799ef193c 37896af7-ad5e-4558-a434-627ae680f6fa -->
# Laravel + React Binance Trading Platform

## Overview

Set up a base Laravel 11 project with React using Inertia.js, including authentication system (login/register) and a dashboard layout. This will serve as the foundation for Binance transaction tracking and ad bot features.

## Implementation Steps

### 1. Project Initialization

- Create new Laravel 11 project with MySQL configuration
- Install Laravel Breeze with Inertia + React stack
- Configure `.env` file with database credentials
- Run migrations to set up authentication tables

### 2. Frontend Setup

- Install Node dependencies (React, Inertia.js, Tailwind CSS)
- Configure Vite for React + Inertia
- Verify Breeze provides: Login, Register, Password Reset, Email Verification pages

### 3. Dashboard Foundation

- Create base dashboard layout with:
- Navigation sidebar/header
- User menu with logout
- Main content area (placeholder for future Binance features)
- Responsive design with Tailwind CSS

### 4. Project Structure

- Set up proper folder structure for future features:
- Controllers for Binance API integration (stub)
- Models for transactions and bot configs (placeholder)
- React components organized by feature

### 5. Documentation

- Create README.md with:
- Installation instructions
- Environment setup
- Development server commands
- Project structure overview
- Next steps for Binance integration

## Key Files to Create/Modify

- `composer.json` - Laravel dependencies
- `package.json` - React and frontend dependencies
- `.env.example` - Environment template
- `app/Http/Controllers/DashboardController.php` - Dashboard logic
- `resources/js/Pages/Dashboard.jsx` - Main dashboard UI
- `resources/js/Layouts/AppLayout.jsx` - App layout wrapper
- `routes/web.php` - Application routes
- `README.md` - Setup documentation

## Technologies Used

- Laravel 11
- React 18
- Inertia.js
- Tailwind CSS
- MySQL
- Vite

### To-dos

- [ ] Initialize Laravel 11 project and install Breeze with Inertia + React
- [ ] Configure environment file and database connection
- [ ] Install and configure Node dependencies and Vite
- [ ] Enhance dashboard with proper layout and navigation for future features
- [ ] Create folder structure and placeholder files for Binance features
- [ ] Create comprehensive README with setup and usage instructions