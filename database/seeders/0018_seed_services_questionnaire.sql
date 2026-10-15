-- =====================================================================
-- Seeder 0018. AWALO GLOBAL Additional Services Questionnaire.
--
-- Creates one published form with twenty-two fields across four
-- steps. Flights, Hotels, Cargo, then the cross-service questions.
--
-- Idempotent. Re-running removes the existing field list for this
-- form and recreates it. The form row is not deleted if it exists;
-- its description and success message update.
--
-- Only inserts when an active store exists. The store_id comes from
-- commerce_stores. If the store table holds more than one active row
-- the form is created for the first one.
-- =====================================================================

SET @store_id := (
    SELECT `id` FROM `commerce_stores`
    WHERE `status` = 'active'
    ORDER BY `id` ASC
    LIMIT 1
);

-- ---------------------------------------------------------------------
-- Step 0. Guard. Nothing inserts if there is no active store.
-- ---------------------------------------------------------------------
SET @store_ok := IF(@store_id IS NULL, 0, 1);

-- ---------------------------------------------------------------------
-- Step 1. The form row. Upsert on (store_id, slug).
-- ---------------------------------------------------------------------
INSERT INTO `commerce_forms`
    (`store_id`, `name`, `slug`, `description`, `status`,
     `success_message`, `notify_email`, `honeypot_enabled`,
     `requires_auth`, `created_by`)
SELECT
    @store_id,
    'AWALO GLOBAL Additional Services Questionnaire',
    'services-questionnaire',
    'Please answer the questions below about the three new services. Your answers help us choose the right tier for each service and give you an accurate quote. The form has five steps and you can answer in stages. If you change your mind about a tier later, send us a new submission or email us directly. Pricing for each tier is shown in the dropdown options.',
    'published',
    'Thank you. Your answers have been received. We will review them and reply within two business days.',
    NULL,
    1,
    0,
    NULL
FROM (SELECT 1) AS `t`
WHERE @store_ok = 1
ON DUPLICATE KEY UPDATE
    `name`            = VALUES(`name`),
    `description`     = VALUES(`description`),
    `status`          = 'published',
    `success_message` = VALUES(`success_message`),
    `honeypot_enabled` = 1,
    `requires_auth`   = 0,
    `deleted_at`      = NULL;

SET @form_id := (
    SELECT `id` FROM `commerce_forms`
    WHERE `store_id` = @store_id
      AND `slug` = 'services-questionnaire'
    LIMIT 1
);

-- ---------------------------------------------------------------------
-- Step 2. Clear existing fields so re-runs replace the list.
-- ---------------------------------------------------------------------
DELETE FROM `commerce_form_fields`
WHERE `form_id` = @form_id;

-- ---------------------------------------------------------------------
-- Step 3. Insert the fields. Twenty-four rows across five steps.
-- ---------------------------------------------------------------------

-- Step 1. Flights.
INSERT INTO `commerce_form_fields`
    (`store_id`, `form_id`, `field_key`, `label`, `input_type`,
     `placeholder`, `help_text`, `is_required`, `options_json`,
     `step`, `sort_order`)
VALUES
    (@store_id, @form_id, 'flight_tier', 'Which flight tier fits what you want?', 'select',
     NULL, NULL, 1,
     JSON_ARRAY(
        JSON_OBJECT('value', '1', 'label', 'Tier 1. Landing page and enquiry form. Fee: $150.'),
        JSON_OBJECT('value', '2', 'label', 'Tier 2. Request form and manual booking. Fee: $250.'),
        JSON_OBJECT('value', '3', 'label', 'Tier 3. Live booking through your supplier API. Fee: $5,000.'),
        JSON_OBJECT('value', '4', 'label', 'Tier 4. Agency platform. Fee: $11,000 to $18,000.')
     ),
     2, 10),
    (@store_id, @form_id, 'flight_supplier', 'Do you have a flight supplier?', 'text',
     'Name the supplier if you have one', 'Leave blank if not sure yet.', 0, NULL, 2, 20),
    (@store_id, @form_id, 'flight_supplier_api', 'Does your supplier provide an API?', 'select',
     NULL, 'For the supplier tiers, the development fee depends on the completeness of your supplier API documentation. A well documented API with a stable sandbox can be integrated in the estimated scope. A poorly documented API, an unstable sandbox, or a vendor that does not answer integration questions can extend the effort by 30 to 50 percent.', 0,
     JSON_ARRAY(
        JSON_OBJECT('value', 'yes', 'label', 'Yes'),
        JSON_OBJECT('value', 'no', 'label', 'No'),
        JSON_OBJECT('value', 'not_sure', 'label', 'Not sure')
     ),
     2, 30),
    (@store_id, @form_id, 'flight_notes', 'Any other detail about the flight service?', 'long_text',
     NULL, NULL, 0, NULL, 2, 40);

-- Step 2. Hotels.
INSERT INTO `commerce_form_fields`
    (`store_id`, `form_id`, `field_key`, `label`, `input_type`,
     `placeholder`, `help_text`, `is_required`, `options_json`,
     `step`, `sort_order`)
VALUES
    (@store_id, @form_id, 'hotel_tier', 'Which hotel tier fits what you want?', 'select',
     NULL, NULL, 1,
     JSON_ARRAY(
        JSON_OBJECT('value', '1', 'label', 'Tier 1. Landing page and enquiry form. Fee: $150.'),
        JSON_OBJECT('value', '2', 'label', 'Tier 2. Static catalogue of your hotels. Fee: $250.'),
        JSON_OBJECT('value', '3', 'label', 'Tier 3. Catalogue with date selection. Fee: $550.'),
        JSON_OBJECT('value', '4', 'label', 'Tier 4. Live booking through a supplier API. Fee: $3,000 and up.')
     ),
     3, 10),
    (@store_id, @form_id, 'hotel_ownership', 'Are the hotels yours, a partner''s, or both?', 'select',
     NULL, NULL, 0,
     JSON_ARRAY(
        JSON_OBJECT('value', 'ours', 'label', 'Our own hotels'),
        JSON_OBJECT('value', 'partner', 'label', 'Partner hotels'),
        JSON_OBJECT('value', 'both', 'label', 'Both')
     ),
     3, 20),
    (@store_id, @form_id, 'hotel_supplier', 'Do you have a hotel supplier?', 'text',
     'Name the supplier if you have one', 'Leave blank if not sure yet.', 0, NULL, 3, 30),
    (@store_id, @form_id, 'hotel_supplier_api', 'Does your supplier provide an API?', 'select',
     NULL, 'For the supplier tier, the development fee depends on the completeness of your supplier API documentation. A well documented API with a stable sandbox can be integrated in the estimated scope. A poorly documented API, an unstable sandbox, or a vendor that does not answer integration questions can extend the effort by 30 to 50 percent.', 0,
     JSON_ARRAY(
        JSON_OBJECT('value', 'yes', 'label', 'Yes'),
        JSON_OBJECT('value', 'no', 'label', 'No'),
        JSON_OBJECT('value', 'not_sure', 'label', 'Not sure')
     ),
     3, 40),
    (@store_id, @form_id, 'hotel_notes', 'Any other detail about the hotel service?', 'long_text',
     NULL, NULL, 0, NULL, 3, 50);

-- Step 3. Cargo.
INSERT INTO `commerce_form_fields`
    (`store_id`, `form_id`, `field_key`, `label`, `input_type`,
     `placeholder`, `help_text`, `is_required`, `options_json`,
     `step`, `sort_order`)
VALUES
    (@store_id, @form_id, 'cargo_tier', 'Which cargo tier fits what you want?', 'select',
     NULL, NULL, 1,
     JSON_ARRAY(
        JSON_OBJECT('value', '1', 'label', 'Tier 1. Landing page and form. Fee: $150.'),
        JSON_OBJECT('value', '2', 'label', 'Tier 2. Detailed quote request. Fee: $250.'),
        JSON_OBJECT('value', '3', 'label', 'Tier 3. Quote, payment, and tracking. Fee: $550.'),
        JSON_OBJECT('value', '4', 'label', 'Tier 4. Live carrier integration. Fee: $1,800 and up per carrier.')
     ),
     4, 10),
    (@store_id, @form_id, 'cargo_pricing_source', 'How do you currently price a shipment?', 'select',
     NULL, NULL, 0,
     JSON_ARRAY(
        JSON_OBJECT('value', 'fixed', 'label', 'Fixed table per weight band and destination'),
        JSON_OBJECT('value', 'weight_distance', 'label', 'Weight and distance'),
        JSON_OBJECT('value', 'live_carrier', 'label', 'Live carrier rate'),
        JSON_OBJECT('value', 'manual', 'label', 'Manual quote per shipment')
     ),
     4, 20),
    (@store_id, @form_id, 'cargo_carrier', 'Do you use a carrier such as DHL, FedEx, or a local provider?', 'text',
     'Name the carrier if you have one', 'Leave blank if not sure yet.', 0, NULL, 4, 30),
    (@store_id, @form_id, 'cargo_carrier_api', 'Does your carrier provide an API?', 'select',
     NULL, 'For the carrier tier, the development fee depends on the completeness of the carrier API documentation. A well documented API with a stable sandbox can be integrated in the estimated scope. A poorly documented API, an unstable sandbox, or a vendor that does not answer integration questions can extend the effort by 30 to 50 percent.', 0,
     JSON_ARRAY(
        JSON_OBJECT('value', 'yes', 'label', 'Yes'),
        JSON_OBJECT('value', 'no', 'label', 'No'),
        JSON_OBJECT('value', 'not_sure', 'label', 'Not sure')
     ),
     4, 40),
    (@store_id, @form_id, 'cargo_notes', 'Any other detail about the cargo service?', 'long_text',
     NULL, NULL, 0, NULL, 4, 50);

-- Step 4. Across all three services.
INSERT INTO `commerce_form_fields`
    (`store_id`, `form_id`, `field_key`, `label`, `input_type`,
     `placeholder`, `help_text`, `is_required`, `options_json`,
     `step`, `sort_order`)
VALUES
    (@store_id, @form_id, 'priority_order', 'Rank the three services in order of priority. 1 is highest.', 'text',
     'For example: Flights 1, Cargo 2, Hotels 3', NULL, 1, NULL, 5, 10),
    (@store_id, @form_id, 'launch_deadline', 'When do you want this live?', 'text',
     'For example: within three months', NULL, 0, NULL, 5, 20),
    (@store_id, @form_id, 'budget_range', 'What is the budget range for all three services?', 'select',
     NULL, NULL, 0,
     JSON_ARRAY(
        JSON_OBJECT('value', 'under_1k', 'label', 'Under $1,000'),
        JSON_OBJECT('value', '1k_3k', 'label', '$1,000 to $3,000'),
        JSON_OBJECT('value', '3k_8k', 'label', '$3,000 to $8,000'),
        JSON_OBJECT('value', '8k_18k', 'label', '$8,000 to $18,000'),
        JSON_OBJECT('value', 'over_18k', 'label', 'Above $18,000'),
        JSON_OBJECT('value', 'undecided', 'label', 'Not decided yet')
     ),
     5, 30),
    (@store_id, @form_id, 'payment_preference', 'How do you prefer to pay for the work?', 'select',
     NULL, NULL, 0,
     JSON_ARRAY(
        JSON_OBJECT('value', 'fixed', 'label', 'Fixed price per service'),
        JSON_OBJECT('value', 'milestone', 'label', 'Milestone based'),
        JSON_OBJECT('value', 'retainer', 'label', 'Monthly retainer'),
        JSON_OBJECT('value', 'mixed', 'label', 'A mix')
     ),
     5, 40),
    (@store_id, @form_id, 'pilot_preference', 'Build one first as a pilot, or all three at once?', 'select',
     NULL, NULL, 0,
     JSON_ARRAY(
        JSON_OBJECT('value', 'pilot', 'label', 'Start with one as a pilot'),
        JSON_OBJECT('value', 'parallel', 'label', 'Build all three together'),
        JSON_OBJECT('value', 'highest_lowest', 'label', 'Build the highest and lowest tiers first to test the market')
     ),
     5, 50),
    (@store_id, @form_id, 'content_readiness', 'Do you have the content ready?', 'select',
     NULL, NULL, 0,
     JSON_ARRAY(
        JSON_OBJECT('value', 'ready', 'label', 'All content ready'),
        JSON_OBJECT('value', 'some', 'label', 'Some content ready'),
        JSON_OBJECT('value', 'placeholders', 'label', 'Use placeholders for now')
     ),
     5, 60),
    (@store_id, @form_id, 'additional_notes', 'Anything else we should know?', 'long_text',
     NULL, NULL, 0, NULL, 5, 70),
    (@store_id, @form_id, 'agree_terms', 'I understand the pricing tiers shown above and that this questionnaire does not commit me to any purchase.', 'yes_no',
     NULL, 'Your answers help us scope the work. They are not an order.', 1, NULL, 5, 80);

