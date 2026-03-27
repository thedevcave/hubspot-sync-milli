---
layout: page
title: Checkout Field Configuration
nav_order: 4
---

# Checkout Fields Configuration

The HubSpot Sync - Milli plugin supports two different checkout field implementations:

## Full Checkout Fields (Default)
- Multiple step conditional form with acquisition source dropdown
- Healthcare provider conversation questions
- Conditional provider referral questions  
- Provider detail collection (name, clinic, state)

## Simplified Checkout Fields
- Single radio button question: "Were you referred by a healthcare provider?"
- Yes/No options only
- Maps to the same HubSpot property: `did_your_provider_refer_you_to_milli_`

## How to Switch

### To Enable Simple Checkout Fields:
1. Open `hubspot-sync-milli.php` 
2. Find the line: `define( 'HUBSPOT_SYNC_MILLI_USE_SIMPLE_CHECKOUT', false );`
3. Change `false` to `true`
4. Save the file

### To Return to Full Checkout Fields:
1. Open `hubspot-sync-milli.php`
2. Find the line: `define( 'HUBSPOT_SYNC_MILLI_USE_SIMPLE_CHECKOUT', true );`
3. Change `true` to `false` 
4. Save the file

## Technical Details

Both implementations:
- Use the same field mappings in settings
- Are compatible with existing HubSpot custom properties
- Support the same sync functionality
- Save data using the same format

### Field Mapping
- Simple version uses only: `provider_referred` → `did_your_provider_refer_you_to_milli_`
- Full version uses all fields: acquisition_source, talked_to_provider, provider_referred, clinician_name, clinic_state, clinic_name

### Files
- Full implementation: `includes/class-checkout-fields.php`
- Simple implementation: `includes/class-checkout-fields-simple.php`
- Switch logic: `hubspot-sync-milli.php` and `includes/class-hubspot-sync-milli.php`