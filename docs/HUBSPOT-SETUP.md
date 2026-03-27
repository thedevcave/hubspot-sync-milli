---
layout: page
title: HubSpot Setup Guide
nav_order: 6
---

# HubSpot Custom Properties Setup

To ensure the abandoned cart sync functionality works correctly, you need to configure the following custom properties in your HubSpot account:

## Required Deal Properties

1. **cart_hash**
   - Property Name: `cart_hash`
   - Property Type: Single-line text
   - Description: Unique identifier for abandoned cart tracking
   - Used to link abandoned carts to converted orders

2. **order_source** 
   - Property Name: `order_source`
   - Property Type: Single-line text  
   - Description: Source of the order (abandoned_cart, converted_from_abandoned_cart, etc.)

## Optional Deal Properties

These properties are used if available but won't cause errors if missing:

3. **tax_amount**
   - Property Name: `tax_amount`
   - Property Type: Number
   - Description: Tax amount for the order

4. **discount_amount**
   - Property Name: `discount_amount` 
   - Property Type: Number
   - Description: Discount amount applied

5. **coupon_codes**
   - Property Name: `coupon_codes`
   - Property Type: Single-line text
   - Description: Coupon codes applied to the order

6. **products**
   - Property Name: `products`
   - Property Type: **Multi-line text** (NOT single-line text)
   - Description: List of products in the cart/order
   - **Important**: Must be multi-line text to handle longer product lists

## Contact Properties

Standard HubSpot contact properties are used:
- email, firstname, lastname, phone, company
- address, city, state, zip, country

## How to Create Properties

1. Go to Settings > Properties in your HubSpot account
2. Select "Deal properties" 
3. Click "Create property"
4. Enter the property name and details above
5. Save the property

## Current Status

The plugin currently bypasses cart hash property searches to avoid 400 errors. Once you create the `cart_hash` property in HubSpot, you can:

1. Uncomment the search lines in `class-sync-manager.php`
2. Remove the temporary TODO comments
3. Enable full abandoned cart deduplication

This will prevent duplicate deals from being created for the same abandoned cart.