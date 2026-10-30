# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Studio Lemus is used by the salon owner, administrators, and employees. They work at a desk, tablet, or phone while attending clients, booking appointments, charging sales, and reviewing the business. The interface must remain understandable for people without technical training.

## Product Purpose

Studio Lemus is an administrative platform for a nail salon. It centralizes appointments, sales, invoices, expenses, earnings, services, users, and notifications so the team can operate the day quickly and the owner can understand the business.

## Positioning

The product joins front-desk operation and salon administration in one Spanish-language workspace, preserving the operational contracts already implemented for appointments, sales, expenses, payroll-related expenses, and reporting.

## Operating Context

The primary operating moments are taking an appointment, attending or charging a client, recording a payment or expense, and reviewing the day. Usage can be interrupted, touch-based, and time-sensitive. Currency is HNL and the product language is Spanish.

## Capabilities and Constraints

- Existing backend endpoints, payloads, Inertia contracts, permission rules, calculations, persistence, and transactional flows are binding.
- The redesign may change visual hierarchy and frontend composition, but not backend behavior.
- Desktop, tablet, and mobile web are supported, starting at 320px.
- Thermal receipt printing remains functionally and visually independent from the application shell.

## Brand Commitments

Studio Lemus is feminine, professional, modern, elegant, warm, and premium without being precious. Official logo source assets are in `resources/images/`. Use only the necessary approved logo presentation for each context; do not force every logo variation into the interface.

## Evidence on Hand

- Existing functional Vue/Inertia/Vuetify interface under `resources/js/`.
- Official logo source files under `resources/images/`, including transparent black and transparent white variations.
- Current responsive screenshots under `docs/screenshots/phase-2a-stabilization/`.
- No customer testimonials, claims, benchmarks, or brand imagery beyond the supplied logo assets are available for invention.

## Product Principles

- Put today's operational decision before administration and reporting.
- Make charging and booking fast, explicit, and recoverable.
- Keep financial information calm, legible, and truthful rather than decorative.
- Use familiar Spanish salon language and visible feedback.
- Let premium character come from restraint, materials, type, and rhythm, never from friction.

## Accessibility & Inclusion

Use reasonable WCAG AA contrast, visible keyboard focus, semantic labels, 44px minimum touch targets where possible, and a reduced-motion alternative. Do not communicate status by color alone.
