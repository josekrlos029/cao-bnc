<!-- cf4a9cee-26a6-47dd-9a9d-0221a3279aaf 9e4518a1-1ba0-416f-bf47-891e8ec4bfb7 -->
# Bot Dashboard with Login and Bot Configuration

## Overview

Remove registration routes and UI, ensure login is functional, create an admin user via migration, and build a dashboard mockup that includes both stats overview and bot configuration management UI.

## Implementation Steps

### 1. Remove Registration Functionality

- Remove registration routes from `routes/auth.php` (lines 15-18)
- Remove "Register" link from `resources/js/Pages/Auth/Login.jsx` (lines 91-96)
- Update `routes/web.php` to remove `canRegister` prop (line 12)
- Keep `RegisteredUserController.php` but routes will be disabled

### 2. Create Admin User Migration

- Create a new seeder: `database/seeders/AdminUserSeeder.php`
- Add admin user with credentials:
- Email: `admin@cao.com` (or username field if preferred)
- Password: `cao2025` (hashed with bcrypt)
- Name: `Admin`
- Update `database/seeders/DatabaseSeeder.php` to call AdminUserSeeder

### 3. Update Dashboard UI

- Enhance `resources/js/Pages/Dashboard.jsx`:
- Keep existing stats overview section at the top
- Add new Bot Configuration section below stats
- Include tabs or navigation for "Overview" and "Bot Configuration"

### 4. Create Bot Configuration UI Mockup

Based on the provided image, create a new component or section in Dashboard that includes:

**Main Sections:**

- **Datos Anuncio** (Ad Data):
- Fiat currency selector (COP, etc.)
- Asset selector (BTC with exchange rate display)
- Operation type (BUY/SELL)
- Min/Max limits
- Payment methods checkboxes (Nequi, BancolombiaA)
- Ad number display with "OBTENER Datos Anuncio" button

- **Configuration Panels:**
- **Posiciones** (Positions): Min/Max input fields
- **Precios** (Prices): Min/Max input fields
- **Dif. USD**: Min/Max input fields with radio button
- **Perfil** (Profile): Radio buttons for Agresivo/Moderado/Conservador
- **Ajuste Ascenso** (Ascent Adjustment): Incr./Dif. input fields

- **Information Panel:**
- Display current USD price
- Show "Mi Precio", "Mi Posición", "Mi Perfil", "Dif. USD"
- Price limits and volume settings with activation checkboxes

- **Position Information:**
- Display section for position-related info

- **Submit Button** at the bottom

### 5. Styling and Layout

- Use Tailwind CSS (already configured)
- Create a clean, modern UI matching the structure from the image
- Make it responsive for different screen sizes
- Use form inputs, radio buttons, checkboxes as shown
- Implement proper spacing and visual hierarchy

## Files to Modify

- `routes/auth.php` - Remove registration routes
- `routes/web.php` - Remove canRegister prop
- `resources/js/Pages/Auth/Login.jsx` - Remove register link
- `resources/js/Pages/Dashboard.jsx` - Add bot configuration UI mockup

## Files to Create

- `database/seeders/AdminUserSeeder.php` - Admin user creation
- Optionally: `resources/js/Components/BotConfiguration.jsx` - Separate component for bot config

## Notes

- Login functionality remains fully functional
- Bot configuration is UI mockup only (no backend integration yet)
- Admin user will be created via seeder for easy login access
- All existing authentication features (password reset, etc.) remain intact

### To-dos

- [ ] Remove registration routes from auth.php and update login page to remove register link
- [ ] Create AdminUserSeeder with credentials (admin@cao.com / cao2025)
- [ ] Update Dashboard.jsx to include both stats overview and bot configuration sections
- [ ] Build bot configuration UI mockup with all sections from the provided image (Datos Anuncio, Posiciones, Precios, Perfil, etc.)
- [ ] Apply Tailwind styling, ensure responsive design, and polish the overall UI