<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="monclubtt-div">
    <div class="monclubtt-introuvable" role="status">
        <svg class="monclubtt-introuvable-figure" viewBox="20 6 88 160" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
            <!-- ombre au sol -->
            <ellipse cx="64" cy="160" rx="34" ry="5" fill="#23344a" opacity="0.08"/>
            <!-- raquette tombée -->
            <g transform="rotate(-24 36 150)">
                <ellipse cx="36" cy="147" rx="9" ry="11" fill="#d34328"/>
                <ellipse cx="36" cy="147" rx="9" ry="11" fill="none" stroke="#ffffff" stroke-width="1.6"/>
                <rect x="33" y="156" width="6" height="12" rx="3" fill="#e7c9a3"/>
            </g>
            <!-- balle qui s'échappe -->
            <circle cx="92" cy="157" r="5" fill="#f4f6f8" stroke="#d9e0e6" stroke-width="1.4"/>
            <!-- jambes -->
            <rect x="51" y="108" width="11" height="40" rx="5.5" fill="#e7b48f"/>
            <rect x="66" y="108" width="11" height="40" rx="5.5" fill="#e7b48f"/>
            <!-- chaussures -->
            <path d="M48 146 h15 v6 q0 4 -4 4 h-11 q-3 0 -3 -3 z" fill="#ffffff" stroke="#d9e0e6" stroke-width="1"/>
            <path d="M65 146 h15 v7 q0 3 -3 3 h-12 v-10 z" fill="#ffffff" stroke="#d9e0e6" stroke-width="1"/>
            <!-- short -->
            <path d="M46 92 h36 v15 q0 4 -4 4 h-9 l-5 -10 -5 10 h-9 q-4 0 -4 -4 z" fill="#23344a"/>
            <!-- bras gauche baissé -->
            <rect x="35" y="60" width="11" height="36" rx="5.5" fill="#e7b48f"/>
            <rect x="35" y="58" width="11" height="13" rx="4" fill="#2b7cb5"/>
            <!-- bras droit baissé -->
            <rect x="82" y="60" width="11" height="36" rx="5.5" fill="#e7b48f"/>
            <rect x="82" y="58" width="11" height="13" rx="4" fill="#2b7cb5"/>
            <!-- torse -->
            <path d="M44 60 q1 -6 7 -7 l26 0 q6 1 7 7 l0 30 q0 5 -6 5 l-28 0 q-6 0 -6 -5 z" fill="#2b7cb5"/>
            <path d="M44 60 q1 -6 7 -7 l4 0 l0 42 l-9 0 q-2 0 -2 -3 z" fill="#1f5e8b"/>
            <path d="M56 53 l8 8 l8 -8 q-8 -3 -16 0 z" fill="#ffffff"/>
            <path d="M64 61 l-5 -6 l5 0 l5 0 z" fill="#d34328"/>
            <!-- cou -->
            <rect x="58" y="46" width="12" height="10" rx="3" fill="#dba883"/>
            <!-- tête -->
            <circle cx="64" cy="36" r="15" fill="#e7b48f"/>
            <!-- yeux -->
            <ellipse cx="58" cy="40" rx="2.2" ry="2.6" fill="#3a2f28"/>
            <ellipse cx="70" cy="40" rx="2.2" ry="2.6" fill="#3a2f28"/>
            <!-- sourcils tristes -->
            <path d="M54 36 L61 34" stroke="#3a2f28" stroke-width="1.6" fill="none" stroke-linecap="round"/>
            <path d="M74 36 L67 34" stroke="#3a2f28" stroke-width="1.6" fill="none" stroke-linecap="round"/>
            <!-- larme -->
            <path d="M57.5 43 q-2.6 4 0 6.6 q2.6 -2.6 0 -6.6 z" fill="#7fbfe8"/>
            <!-- bouche triste -->
            <path d="M59 48 q5 -3.5 10 0" stroke="#c98c63" stroke-width="1.7" fill="none" stroke-linecap="round"/>
            <!-- cheveux -->
            <path d="M49 35 q1 -18 15 -18 q14 0 15 18 q-5 -7 -15 -7 q-10 0 -15 7 z" fill="#3a2f28"/>
        </svg>
        <h4 class="monclubtt-introuvable-titre">
            <?php echo esc_html__( 'Poule not available', 'mon-club-tt' ); ?>
        </h4>
        <p class="monclubtt-introuvable-message">
            <?php echo esc_html__( 'The standings and results for this team are not available at the moment. This usually happens between two phases of the championship or once the season is over, when the federation removes the pools from its database.', 'mon-club-tt' ); ?>
        </p>
        <p class="monclubtt-introuvable-message">
            <?php echo esc_html__( 'This page will display the data again automatically as soon as a new pool is available.', 'mon-club-tt' ); ?>
        </p>
    </div>
</div>
