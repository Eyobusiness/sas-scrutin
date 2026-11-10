<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Services/ElectionService.php';

$currentElection = $currentElection ?? ($derniereElection ?? null);
if ($currentElection && empty($phaseInfo)) {
    $phaseInfo = $currentElection['phase_info'] ?? (new ElectionService())->getCurrentPhase($currentElection);
}
$isCloturee = $isCloturee ?? (!empty($phaseInfo['is_closed']));
$resultatsPublics = $resultatsPublics ?? (!empty($phaseInfo['resultats_publics']));
$publicCase = $isCloturee ? 5 : (int)($phaseInfo['public_case'] ?? 1);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light" data-bs-theme="light" style="color-scheme: light;">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="only light">
    <meta name="theme-color" content="#0b1f4d">
    <title>Résultats Officiels | SAS Élection</title>

    <link rel="icon" type="image/png" href="assets/images/logo/logosas.png">
    <link rel="apple-touch-icon" href="assets/images/logo/logosas.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/results.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        document.documentElement.classList.add('js');
    </script>

    <style>
        :root {
            color-scheme: light only;
            --navy-900: #081a42;
            --navy-800: #0b1f4d;
            --navy-700: #12306f;
            --blue-600: #1d4ed8;
            --blue-500: #2563eb;
            --sky-400: #38bdf8;
            --gold-500: #f5b301;
            --gold-300: #ffe27a;
            --gold-100: #fff6d6;
            --green-700: #047857;
            --green-600: #059669;
            --green-500: #10b981;
            --green-100: #d1fae5;
            --orange-700: #c2410c;
            --orange-600: #ea580c;
            --orange-500: #f97316;
            --orange-100: #ffedd5;
            --silver: #94a3b8;
            --bronze: #c2763a;
            --ink: #0f172a;
            --muted: #5b6b86;
            --glass: rgba(255, 255, 255, .78);
            --edge: rgba(255, 255, 255, .8);
            --line: rgba(11, 31, 77, .09);
            --shadow: 0 10px 34px -14px rgba(11, 31, 77, .28);
            --shadow-lg: 0 28px 64px -22px rgba(11, 31, 77, .42);
            --ease: cubic-bezier(.2, .8, .2, 1);
        }

        /* Thème clair uniquement : on neutralise tout assombrissement automatique */
        html,
        body {
            color-scheme: light only;
        }

        body,
        body.results-page {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: var(--ink) !important;
            background-color: #eef3fc !important;
            background-image:
                linear-gradient(rgba(243, 247, 255, .9), rgba(243, 247, 255, .95)),
                url('https://electionsas.l2vservices.com/assets/images/logo/sas.jpg') !important;
            background-size: cover !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
            background-attachment: fixed !important;
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        @media (max-width: 767.98px) {

            body,
            body.results-page {
                background-attachment: scroll !important;
            }
        }

        /* ---------- Décor ---------- */
        .rs-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .42;
            z-index: 0;
            pointer-events: none;
            animation: rsFloat 20s ease-in-out infinite;
        }

        .rs-orb.o1 {
            width: 400px;
            height: 400px;
            background: #93c5fd;
            top: -140px;
            left: -120px;
        }

        .rs-orb.o2 {
            width: 340px;
            height: 340px;
            background: #fde68a;
            bottom: -120px;
            right: -90px;
            animation-delay: -7s;
        }

        .rs-orb.o3 {
            width: 260px;
            height: 260px;
            background: #c4b5fd;
            top: 48%;
            right: 18%;
            opacity: .22;
            animation-delay: -12s;
        }

        @keyframes rsFloat {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            33% {
                transform: translate(40px, 30px) scale(1.08);
            }

            66% {
                transform: translate(-30px, 46px) scale(.95);
            }
        }

        .results-content-wrap {
            position: relative;
            z-index: 1;
        }

        /* ---------- Révélation ---------- */
        .js .rs-reveal {
            opacity: 0;
            transform: translateY(26px) scale(.985);
            transition: opacity .75s var(--ease), transform .75s var(--ease);
            transition-delay: var(--d, 0s);
        }

        .js .rs-reveal.in {
            opacity: 1;
            transform: none;
        }

        /* ---------- Cartes ---------- */
        .results-card,
        .kpi-card-res,
        .ranking-card-item {
            background: var(--glass) !important;
            backdrop-filter: blur(16px) saturate(170%);
            -webkit-backdrop-filter: blur(16px) saturate(170%);
            border: 1px solid var(--edge) !important;
            border-radius: 22px !important;
            box-shadow: var(--shadow) !important;
            color: var(--ink);
        }

        .results-heading {
            color: var(--navy-800) !important;
            letter-spacing: -.01em;
        }

        .results-text-muted {
            color: var(--muted) !important;
        }

        h1,
        h2,
        h3,
        .h2,
        .h4,
        .h5 {
            font-family: 'Fraunces', Georgia, serif;
        }

        .h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* ---------- En-tête ---------- */
        .results-header-box {
            position: relative;
            overflow: hidden;
            border: 0 !important;
            border-radius: 28px !important;
            padding: 1.5rem 1.7rem;
            background:
                radial-gradient(circle at 90% 0%, rgba(245, 179, 1, .28), transparent 45%),
                linear-gradient(120deg, var(--navy-900), var(--navy-700) 55%, var(--blue-600)) !important;
            box-shadow: var(--shadow-lg);
        }

        .results-header-box::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, .16) 50%, transparent 70%);
            transform: translateX(-100%);
            animation: rsShine 7s ease-in-out infinite;
        }

        @keyframes rsShine {

            0%,
            55% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(100%);
            }
        }

        .results-header-box>* {
            position: relative;
            z-index: 1;
        }

        .results-brand-logo {
            width: 64px;
            height: 64px;
            object-fit: contain;
            padding: 7px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 26px rgba(0, 0, 0, .3);
            animation: rsBob 4s ease-in-out infinite;
        }

        @keyframes rsBob {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        .results-title {
            color: #fff !important;
            font-weight: 700;
            letter-spacing: -.015em;
            font-size: clamp(1.25rem, 3.4vw, 1.7rem);
        }

        .rs-eyebrow {
            display: block;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--gold-300);
            margin-bottom: .2rem;
        }

        .rs-head-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem;
            justify-content: center;
            width: 100%;
        }

        .rs-head-actions .btn {
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .45);
            background: rgba(255, 255, 255, .1);
            backdrop-filter: blur(6px);
            padding: .4rem 1.15rem;
        }

        .rs-head-actions .btn:hover {
            background: #fff;
            color: var(--navy-800);
        }

        /* ---------- Boutons / badges ---------- */
        .btn {
            border-radius: 50px !important;
            font-weight: 600;
            transition: transform .25s, box-shadow .25s, background .25s;
        }

        .btn:hover {
            transform: translateY(-3px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--blue-500), var(--blue-600)) !important;
            border: 0 !important;
            box-shadow: 0 10px 22px -8px rgba(37, 99, 235, .65);
        }

        .btn-success {
            border: 0;
            box-shadow: 0 10px 22px -8px rgba(5, 150, 105, .6);
        }

        .badge {
            border-radius: 50px !important;
            font-weight: 700;
            letter-spacing: .02em;
        }

        /* ---------- Icônes d'état ---------- */
        .rs-state-icon {
            position: relative;
            width: 84px !important;
            height: 84px !important;
            margin-bottom: 1.2rem !important;
        }

        .rs-state-icon::before,
        .rs-state-icon::after {
            content: '';
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            border: 2px solid currentColor;
            opacity: .35;
            animation: rsPulse 2.6s ease-out infinite;
        }

        .rs-state-icon::after {
            animation-delay: 1.3s;
        }

        @keyframes rsPulse {
            0% {
                transform: scale(.85);
                opacity: .5;
            }

            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }

        .rs-state-icon i {
            animation: rsBob 3s ease-in-out infinite;
        }

        .rs-spin i {
            animation: rsSpin 6s linear infinite !important;
        }

        @keyframes rsSpin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ---------- Compte à rebours ---------- */
        .rs-countdown {
            display: flex;
            gap: .7rem;
            justify-content: center;
            margin: 0 auto 1.6rem;
        }

        .rs-cd-box {
            min-width: 76px;
            padding: .75rem .4rem;
            border-radius: 16px;
            color: #fff;
            background: linear-gradient(160deg, var(--navy-800), var(--blue-600));
            box-shadow: 0 12px 26px -10px rgba(11, 31, 77, .55);
        }

        .rs-cd-box b {
            display: block;
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .rs-cd-box span {
            font-size: .64rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            opacity: .8;
        }

        /* ---------- Sélecteur de tours ---------- */
        .tour-switch {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .tour-switch .btn {
            padding: .4rem 1rem;
        }

        .tour-switch .btn-outline-primary {
            background: rgba(255, 255, 255, .7);
        }

        /* ---------- Bandeau de certification ---------- */
        .alert {
            border-radius: 18px !important;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }

        /* ================== PROCLAMATIONS ================== */
        .winner-proclamation-banner,
        .equality-comite-banner {
            position: relative;
            overflow: hidden;
            border-radius: 30px !important;
            isolation: isolate;
        }

        /* --- Victoire : émeraude profond --- */
        .winner-proclamation-banner {
            background:
                radial-gradient(circle at 15% 0%, rgba(255, 226, 122, .22), transparent 45%),
                linear-gradient(135deg, #03342a, #065f46 55%, #059669) !important;
            box-shadow: var(--shadow-lg), 0 0 0 1px rgba(245, 179, 1, .4);
        }

        .winner-proclamation-banner::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            left: -50%;
            top: -50%;
            z-index: -1;
            background: conic-gradient(from 0deg, transparent, rgba(245, 179, 1, .22), transparent 28%);
            animation: rsSpin 12s linear infinite;
        }

        .winner-proclamation-banner h2 {
            font-weight: 800;
            letter-spacing: -.02em;
            text-shadow: 0 6px 24px rgba(0, 0, 0, .35);
            font-size: clamp(1.8rem, 5vw, 2.6rem);
        }

        .winner-crown-badge {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            padding: .55rem 1.3rem;
            border-radius: 50px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: #4a3200;
            background: linear-gradient(135deg, var(--gold-300), var(--gold-500) 60%, #e69b00);
            animation: rsGlow 2.4s ease-in-out infinite;
        }

        .winner-crown-badge i {
            display: inline-block;
            animation: rsWiggle 2.4s ease-in-out infinite;
        }

        @keyframes rsGlow {

            0%,
            100% {
                box-shadow: 0 8px 24px -4px rgba(245, 179, 1, .55);
            }

            50% {
                box-shadow: 0 8px 40px 2px rgba(245, 179, 1, .95);
            }
        }

        @keyframes rsWiggle {

            0%,
            100% {
                transform: rotate(0) scale(1);
            }

            25% {
                transform: rotate(-12deg) scale(1.15);
            }

            75% {
                transform: rotate(12deg) scale(1.15);
            }
        }

        .winner-official-sentence-box {
            display: inline-block;
            max-width: 680px;
            margin-top: .6rem;
            padding: 1rem 1.4rem;
            border-radius: 18px;
            color: #ecfdf5;
            font-size: 1rem;
            line-height: 1.6;
            font-weight: 500;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .28);
            backdrop-filter: blur(8px);
        }

        #rsConfetti {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        /* --- Égalité : orange, sans coupe --- */
        .equality-comite-banner {
            background:
                repeating-linear-gradient(-45deg, rgba(234, 88, 12, .06) 0 14px, transparent 14px 28px),
                linear-gradient(135deg, #fff7ed, #ffedd5 60%, #fed7aa) !important;
            border: 1px solid rgba(234, 88, 12, .35);
            box-shadow: var(--shadow-lg);
        }

        .equality-comite-banner::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 8px;
            background: linear-gradient(180deg, var(--orange-500), var(--orange-700));
            animation: rsBlinkBar 2.2s ease-in-out infinite;
        }

        @keyframes rsBlinkBar {
            50% {
                opacity: .55;
            }
        }

        .equality-comite-banner h2 {
            color: #9a3412 !important;
            font-weight: 800;
            letter-spacing: -.01em;
        }

        .equality-comite-instruction {
            display: inline-flex;
            align-items: center;
            padding: .85rem 1.6rem;
            border-radius: 50px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            letter-spacing: .05em;
            font-size: .92rem;
            color: #fff;
            background: linear-gradient(135deg, var(--orange-500), var(--orange-700));
            box-shadow: 0 12px 28px -10px rgba(194, 65, 12, .7);
            animation: rsAlert 2.2s ease-in-out infinite;
        }

        .equality-comite-instruction i {
            animation: rsWiggle 2s ease-in-out infinite;
        }

        @keyframes rsAlert {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.04);
                box-shadow: 0 16px 36px -8px rgba(194, 65, 12, .85);
            }
        }

        /* ---------- KPI ---------- */
        .kpi-section {
            margin-bottom: 1.8rem;
        }

        .kpi-card-res {
            padding: 1.35rem .8rem !important;
            position: relative;
            overflow: hidden;
            transition: transform .35s var(--ease), box-shadow .35s;
        }

        .kpi-card-res::after {
            content: '';
            position: absolute;
            left: 14%;
            right: 14%;
            bottom: 0;
            height: 3px;
            border-radius: 3px;
            background: linear-gradient(90deg, var(--sky-400), var(--blue-600));
            transform: scaleX(0);
            transition: transform .45s var(--ease);
        }

        .kpi-card-res:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg) !important;
        }

        .kpi-card-res:hover::after {
            transform: scaleX(1);
        }

        .kpi-icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: .7rem;
            transition: transform .4s;
        }

        .kpi-card-res:hover .kpi-icon-wrap {
            transform: rotate(-8deg) scale(1.12);
        }

        .kpi-val {
            font-family: 'Fraunces', Georgia, serif;
            font-size: 2.05rem;
            font-weight: 800;
            color: var(--navy-800);
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }

        .kpi-lbl {
            font-size: .74rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-top: .3rem;
        }

        .kpi-ring {
            position: relative;
            width: 64px;
            height: 64px;
            margin: 0 auto .5rem;
        }

        .kpi-ring svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .kpi-ring circle {
            fill: none;
            stroke-width: 4.5;
            stroke-linecap: round;
        }

        .kpi-ring .bg {
            stroke: rgba(11, 31, 77, .09);
        }

        .kpi-ring .fg {
            stroke: url(#rsRingGrad);
            stroke-dasharray: 100;
            stroke-dashoffset: calc(100 - var(--p, 0));
            transition: stroke-dashoffset 1.8s var(--ease) .3s;
        }

        .js .kpi-ring .fg {
            stroke-dashoffset: 100;
        }

        .js .in .kpi-ring .fg,
        .js .kpi-ring.go .fg {
            stroke-dashoffset: calc(100 - var(--p, 0));
        }

        .kpi-ring i {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: var(--gold-500);
        }

        /* ---------- Podium ---------- */
        .podium-container {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 1rem;
            margin: 2.8rem 0 2.2rem;
            padding-top: 1.5rem;
        }

        .podium-container.podium-single .podium-card {
            max-width: 320px;
        }

        .podium-card {
            position: relative;
            flex: 1 1 0;
            max-width: 290px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.3rem 1rem 1.3rem;
            border-radius: 26px 26px 12px 12px;
            transform-origin: bottom center;
            background: var(--glass);
            backdrop-filter: blur(14px);
            border: 1px solid var(--edge);
            box-shadow: var(--shadow);
            transition: transform .4s var(--ease), box-shadow .4s;
        }

        .podium-card:hover {
            transform: translateY(-10px) !important;
            box-shadow: var(--shadow-lg);
        }

        .podium-gold {
            min-height: 345px;
            background: linear-gradient(180deg, #fff8dc, rgba(255, 255, 255, .85)) !important;
            border-top: 5px solid var(--gold-500) !important;
        }

        .podium-silver {
            min-height: 292px;
            background: linear-gradient(180deg, #f1f5f9, rgba(255, 255, 255, .85)) !important;
            border-top: 5px solid var(--silver) !important;
        }

        .podium-bronze {
            min-height: 256px;
            background: linear-gradient(180deg, #fbebdc, rgba(255, 255, 255, .85)) !important;
            border-top: 5px solid var(--bronze) !important;
        }

        /* Victoire : vert */
        .podium-winner-green {
            background: linear-gradient(180deg, #d1fae5, rgba(255, 255, 255, .88)) !important;
            border-top: 5px solid var(--green-600) !important;
            box-shadow: 0 24px 60px -20px rgba(5, 150, 105, .55);
        }

        /* Égalité : orange */
        .podium-equality-orange {
            background: linear-gradient(180deg, #ffedd5, rgba(255, 255, 255, .88)) !important;
            border-top: 5px solid var(--orange-500) !important;
            box-shadow: 0 24px 60px -20px rgba(234, 88, 12, .5);
        }

        .js .podium-card {
            opacity: 0;
            transform: scaleY(.2) translateY(60px);
            transition: opacity .6s, transform .95s cubic-bezier(.2, 1.1, .3, 1), box-shadow .4s;
            transition-delay: var(--d, 0s);
        }

        .js .podium-card.in {
            opacity: 1;
            transform: none;
        }

        .podium-crown-badge {
            position: absolute;
            top: -27px;
            left: 50%;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #4a3200;
            background: linear-gradient(135deg, var(--gold-300), var(--gold-500));
            box-shadow: 0 10px 24px rgba(245, 179, 1, .7);
            animation: rsBobC 2.6s ease-in-out infinite;
        }

        @keyframes rsBobC {

            0%,
            100% {
                transform: translate(-50%, 0);
            }

            50% {
                transform: translate(-50%, -7px);
            }
        }

        .podium-avatar-wrap {
            position: relative;
            width: 96px;
            height: 96px;
            margin: 0 auto 1rem;
        }

        .podium-gold .podium-avatar-wrap {
            width: 114px;
            height: 114px;
        }

        .podium-avatar,
        .podium-avatar-initials {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 10px 26px -6px rgba(11, 31, 77, .4);
        }

        .podium-gold .podium-avatar,
        .podium-gold .podium-avatar-initials {
            box-shadow: 0 0 0 4px var(--gold-500), 0 14px 32px -6px rgba(245, 179, 1, .7);
        }

        .podium-silver .podium-avatar,
        .podium-silver .podium-avatar-initials {
            box-shadow: 0 0 0 4px var(--silver), 0 10px 26px -6px rgba(100, 116, 139, .5);
        }

        .podium-bronze .podium-avatar,
        .podium-bronze .podium-avatar-initials {
            box-shadow: 0 0 0 4px var(--bronze), 0 10px 26px -6px rgba(194, 118, 58, .5);
        }

        .podium-winner-green .podium-avatar,
        .podium-winner-green .podium-avatar-initials {
            box-shadow: 0 0 0 4px var(--green-500), 0 14px 32px -6px rgba(5, 150, 105, .65);
        }

        .podium-equality-orange .podium-avatar,
        .podium-equality-orange .podium-avatar-initials {
            box-shadow: 0 0 0 4px var(--orange-500), 0 14px 32px -6px rgba(234, 88, 12, .6);
        }

        .podium-avatar-initials {
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Fraunces', serif;
            font-weight: 800;
            font-size: 1.9rem;
            color: #fff;
            background: linear-gradient(135deg, #3b82f6, var(--navy-800));
        }

        .podium-rank-badge {
            position: absolute;
            bottom: -6px;
            right: -8px;
            min-width: 38px;
            height: 38px;
            padding: 0 .55rem;
            border-radius: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: .85rem;
            color: #fff;
            border: 3px solid #fff;
            background: linear-gradient(135deg, #3b82f6, var(--navy-800));
        }

        .podium-gold .podium-rank-badge {
            background: linear-gradient(135deg, #ffd23f, #e69b00);
            color: #4a3200;
        }

        .podium-silver .podium-rank-badge {
            background: linear-gradient(135deg, #cbd5e1, #64748b);
        }

        .podium-bronze .podium-rank-badge {
            background: linear-gradient(135deg, #e09a5c, #9a5522);
        }

        .podium-winner-green .podium-rank-badge {
            background: linear-gradient(135deg, var(--green-500), var(--green-700));
            color: #fff;
        }

        .podium-equality-orange .podium-rank-badge {
            background: linear-gradient(135deg, var(--orange-500), var(--orange-700));
            color: #fff;
        }

        .podium-name {
            font-family: 'Fraunces', serif;
            font-size: 1.08rem;
            font-weight: 700;
            color: var(--navy-800);
            margin: 0 0 .8rem;
            line-height: 1.25;
        }

        .podium-score-box {
            padding-top: .8rem;
            border-top: 1px dashed rgba(11, 31, 77, .16);
        }

        .podium-votes-count {
            font-family: 'Fraunces', serif;
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--navy-800);
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .podium-gold .podium-votes-count {
            font-size: 2.9rem;
            background: linear-gradient(135deg, #e69b00, #b45309);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .podium-winner-green .podium-votes-count {
            background: linear-gradient(135deg, var(--green-500), var(--green-700));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .podium-equality-orange .podium-votes-count {
            background: linear-gradient(135deg, var(--orange-500), var(--orange-700));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .podium-percent-badge {
            display: inline-block;
            margin-top: .55rem;
            padding: .3rem .95rem;
            border-radius: 50px;
            font-weight: 800;
            font-size: .85rem;
            color: var(--navy-800);
            background: rgba(37, 99, 235, .12);
        }

        .podium-gold .podium-percent-badge {
            background: var(--gold-100);
            color: #8a5a00;
        }

        .podium-winner-green .podium-percent-badge {
            background: var(--green-100);
            color: var(--green-700);
        }

        .podium-equality-orange .podium-percent-badge {
            background: var(--orange-100);
            color: var(--orange-700);
        }

        /* ---------- Classement ---------- */
        .ranking-card-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.2rem !important;
            margin-bottom: .85rem;
            position: relative;
            overflow: hidden;
            transition: transform .3s var(--ease), box-shadow .3s;
        }

        .ranking-card-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background: linear-gradient(180deg, var(--sky-400), var(--blue-600));
            opacity: .85;
        }

        .ranking-card-item:hover {
            transform: translateX(8px) scale(1.01);
            box-shadow: var(--shadow-lg) !important;
        }

        .ranking-winner-green::before {
            background: linear-gradient(180deg, var(--green-500), var(--green-700));
        }

        .ranking-winner-green {
            background: linear-gradient(90deg, rgba(209, 250, 229, .85), var(--glass)) !important;
        }

        .ranking-equality-orange::before {
            background: linear-gradient(180deg, var(--orange-500), var(--orange-700));
        }

        .ranking-equality-orange {
            background: linear-gradient(90deg, rgba(255, 237, 213, .9), var(--glass)) !important;
        }

        .ranking-rank-badge {
            flex: 0 0 auto;
            min-width: 44px;
            height: 44px;
            padding: 0 .6rem;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, #3b82f6, var(--navy-800));
            box-shadow: 0 8px 16px -6px rgba(37, 99, 235, .55);
        }

        .ranking-winner-green .ranking-rank-badge {
            background: linear-gradient(135deg, var(--green-500), var(--green-700));
            box-shadow: 0 8px 16px -6px rgba(5, 150, 105, .6);
        }

        .ranking-equality-orange .ranking-rank-badge {
            background: linear-gradient(135deg, var(--orange-500), var(--orange-700));
            box-shadow: 0 8px 16px -6px rgba(234, 88, 12, .6);
        }

        .ranking-avatar,
        .ranking-avatar-initials {
            flex: 0 0 auto;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 4px 12px rgba(11, 31, 77, .25);
        }

        .ranking-avatar-initials {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, #60a5fa, #1e40af);
        }

        .ranking-bar-track {
            height: 10px;
            margin-top: .6rem;
            border-radius: 50px;
            background: rgba(11, 31, 77, .08);
            overflow: hidden;
        }

        .ranking-bar-fill {
            position: relative;
            height: 100%;
            width: var(--w, 0%);
            border-radius: 50px;
            background: linear-gradient(90deg, #38bdf8, #2563eb, #1e3a8a);
            background-size: 200% 100%;
            animation: rsFlow 3s linear infinite;
        }

        .ranking-bar-fill.bar-winner-green {
            background: linear-gradient(90deg, #6ee7b7, #10b981, #047857);
            background-size: 200% 100%;
        }

        .ranking-bar-fill.bar-equality-orange {
            background: linear-gradient(90deg, #fdba74, #f97316, #c2410c);
            background-size: 200% 100%;
        }

        .js .ranking-bar-fill {
            width: 0;
            transition: width 1.5s var(--ease);
            transition-delay: var(--d, 0s);
        }

        .js .in .ranking-bar-fill {
            width: var(--w, 0%);
        }

        .ranking-bar-fill::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 24px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .75));
            border-radius: 50px;
        }

        @keyframes rsFlow {
            to {
                background-position: -200% 0;
            }
        }

        /* ---------- Décisions du bureau ---------- */
        .decision-item {
            position: relative;
            padding: 1rem 1.2rem 1rem 1.4rem;
            margin-bottom: .75rem;
            border-radius: 16px;
            background: rgba(255, 255, 255, .85);
            border: 1px solid var(--line);
            box-shadow: 0 6px 18px -12px rgba(11, 31, 77, .35);
        }

        .decision-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 12px;
            bottom: 12px;
            width: 4px;
            border-radius: 4px;
            background: linear-gradient(180deg, var(--blue-500), var(--navy-800));
        }

        /* ---------- Tableau historique ---------- */
        .results-card .table {
            --bs-table-bg: transparent;
            --bs-table-color: var(--ink);
            --bs-table-hover-bg: rgba(37, 99, 235, .05);
        }

        .results-card .table thead th {
            background: rgba(11, 31, 77, .05) !important;
            color: var(--navy-800);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            border: 0;
            padding: .8rem .75rem;
        }

        .results-card .table thead th:first-child {
            border-radius: 12px 0 0 12px;
        }

        .results-card .table thead th:last-child {
            border-radius: 0 12px 12px 0;
        }

        .results-card .table td {
            border-color: var(--line);
            padding: .85rem .75rem;
        }

        .results-card .table tbody tr {
            transition: background .25s;
        }

        .results-card .table tbody tr:hover {
            background: rgba(37, 99, 235, .05);
        }

        /* ---------- Pied de page ---------- */
        .rs-footer {
            text-align: center;
            margin-top: 2.2rem;
            font-size: .78rem;
            color: var(--muted);
        }

        .rs-footer i {
            color: var(--gold-500);
        }

        /* ---------- Mobile ---------- */
        @media (max-width: 767.98px) {
            .podium-container {
                flex-direction: column;
                align-items: stretch;
                gap: 1.7rem;
            }

            .podium-card {
                max-width: none;
                min-height: 0 !important;
                flex-direction: row;
                align-items: center;
                text-align: left;
                gap: 1rem;
                padding: 1.2rem !important;
            }

            .podium-card:nth-child(1) {
                order: 1 !important;
            }

            .podium-card:nth-child(2) {
                order: 2 !important;
            }

            .podium-card:nth-child(3) {
                order: 3 !important;
            }

            .podium-avatar-wrap,
            .podium-gold .podium-avatar-wrap {
                width: 72px;
                height: 72px;
                margin: 0;
                flex: 0 0 auto;
            }

            .podium-score-box {
                border-top: 0;
                padding: 0;
                margin-left: auto;
                text-align: right;
            }

            .podium-votes-count,
            .podium-gold .podium-votes-count {
                font-size: 1.8rem;
            }

            .podium-crown-badge {
                top: -22px;
                left: 44px;
            }

            .rs-cd-box {
                min-width: 62px;
            }

            .rs-cd-box b {
                font-size: 1.35rem;
            }

            .kpi-val {
                font-size: 1.65rem;
            }

            .winner-official-sentence-box {
                font-size: .92rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
            }

            .js .rs-reveal,
            .js .podium-card {
                opacity: 1;
                transform: none;
            }

            .js .ranking-bar-fill {
                width: var(--w, 0%);
            }

            .js .kpi-ring .fg {
                stroke-dashoffset: calc(100 - var(--p, 0));
            }
        }

        /* ---------- Impression / PDF officiel ---------- */
        @media print {

            body,
            body.results-page {
                background: #fff !important;
            }

            .rs-orb,
            .rs-head-actions,
            #rsConfetti,
            .btn,
            .tour-switch,
            .rs-countdown {
                display: none !important;
            }

            .results-card,
            .kpi-card-res,
            .ranking-card-item,
            .podium-card {
                backdrop-filter: none !important;
                box-shadow: none !important;
                border: 1px solid #d5dbe8 !important;
                break-inside: avoid;
            }

            .js .rs-reveal,
            .js .podium-card {
                opacity: 1 !important;
                transform: none !important;
            }

            .js .ranking-bar-fill {
                width: var(--w, 0%) !important;
            }

            .js .kpi-ring .fg {
                stroke-dashoffset: calc(100 - var(--p, 0)) !important;
            }

            .results-header-box,
            .winner-proclamation-banner,
            .equality-comite-banner,
            .podium-card,
            .ranking-bar-fill,
            .rs-cd-box {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            *,
            *::before,
            *::after {
                animation: none !important;
            }
        }
    </style>
</head>

<body class="results-page py-4">

    <div class="rs-orb o1"></div>
    <div class="rs-orb o2"></div>
    <div class="rs-orb o3"></div>

    <!-- Dégradé partagé pour l'anneau de participation -->
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <linearGradient id="rsRingGrad" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#38bdf8" />
                <stop offset="100%" stop-color="#1d4ed8" />
            </linearGradient>
        </defs>
    </svg>

    <div class="container results-content-wrap pb-5" style="max-width: 1000px;">

        <!-- En-tête officiel -->
        <header class="results-header-box mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3 rs-reveal">
            <div class="d-flex align-items-center gap-3">
                <img src="assets/images/logo/logosas.png" alt="Logo SAS" class="results-brand-logo">
                <div>
                    <span class="rs-eyebrow">Proclamation officielle</span>
                    <h1 class="results-title mb-0">Résultats Officiels du Scrutin</h1>
                </div>
            </div>
            <div class="rs-head-actions">
                <a href="index.php?page=home" class="btn btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Accueil
                </a>
                <?php if ($isCloturee): ?>
                    <button type="button" class="btn btn-sm" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Imprimer / PDF
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!$currentElection): ?>
            <div class="results-card p-5 text-center rs-reveal">
                <div class="rounded-circle bg-secondary-subtle text-secondary d-inline-flex align-items-center justify-content-center mb-3 rs-state-icon">
                    <i class="bi bi-calendar-x fs-1"></i>
                </div>
                <h2 class="h5 fw-bold results-heading">Aucun scrutin enregistré</h2>
                <p class="results-text-muted mb-0">Aucune élection n'est actuellement disponible dans le système.</p>
            </div>
        <?php else: ?>

            <!-- Bandeau d'identification en lecture seule du dernier scrutin de référence -->
            <div class="results-card p-3 p-md-4 mb-4 rs-reveal" style="--d:.1s">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <span class="results-text-muted small text-uppercase fw-bold d-block mb-1">
                            <i class="bi bi-bookmark-star-fill text-primary me-1"></i> Dernier scrutin de référence
                        </span>
                        <h2 class="h4 fw-bold mb-1 results-heading"><?= SecurityHelper::e($currentElection['titre']) ?></h2>
                        <p class="results-text-muted small mb-0">
                            Période de vote : Du <strong><?= date('d/m/Y à H:i', strtotime($currentElection['date_debut_vote'] ?? $currentElection['date_debut'])) ?></strong>
                            au <strong><?= date('d/m/Y à H:i', strtotime($currentElection['date_fin_vote'] ?? $currentElection['date_fin'])) ?></strong>
                        </p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <?php if ($phaseInfo): ?>
                            <span class="badge bg-<?= SecurityHelper::e($phaseInfo['badge']) ?> px-3 py-2">
                                <?= SecurityHelper::e($phaseInfo['label']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($resultatsPublics): ?>
                            <?php if (!empty($currentElection['resultats_certifies'])): ?>
                                <span class="badge bg-success px-3 py-2">
                                    <i class="bi bi-patch-check-fill me-1"></i> Résultats officiels certifiés
                                </span>
                            <?php elseif (!empty($currentElection['etat_egalite_persistante'])): ?>
                                <span class="badge bg-warning text-dark px-3 py-2">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Égalité persistante
                                </span>
                            <?php else: ?>
                                <span class="badge bg-primary px-3 py-2">
                                    <i class="bi bi-check2-circle me-1"></i> Résultats publiés
                                </span>
                            <?php endif; ?>
                        <?php elseif ($isCloturee): ?>
                            <span class="badge bg-secondary px-3 py-2">
                                <i class="bi bi-hourglass-split me-1"></i> Résultats en attente de proclamation
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($isCloturee && !$resultatsPublics): ?>
                <div class="results-card p-4 p-md-5 text-center rs-reveal" style="--d:.2s">
                    <div class="rounded-circle bg-secondary-subtle text-secondary d-inline-flex align-items-center justify-content-center mx-auto mb-3 rs-state-icon">
                        <i class="bi bi-hourglass-split fs-1"></i>
                    </div>
                    <span class="badge bg-secondary px-3 py-2 mx-auto mb-2 d-table">Scrutin clôturé</span>
                    <h3 class="h4 fw-bold mb-2 results-heading">Les résultats ne sont pas encore proclamés</h3>
                    <p class="results-text-muted mx-auto mb-0" style="max-width: 560px;">
                        Le scrutin « <?= SecurityHelper::e($currentElection['titre']) ?> » est clôturé. Les résultats seront affichés après leur certification par le bureau.
                    </p>
                </div>

            <?php elseif ($publicCase === 1): ?>
                <!-- CAS 1 : Le dernier scrutin est en préparation -->
                <div class="results-card p-4 p-md-5 text-center rs-reveal" style="--d:.2s">
                    <div class="rounded-circle bg-secondary-subtle text-secondary d-inline-flex align-items-center justify-content-center mx-auto mb-3 rs-state-icon rs-spin">
                        <i class="bi bi-gear-wide-connected fs-1"></i>
                    </div>
                    <span class="badge bg-secondary px-3 py-2 mx-auto mb-2 d-table">Phase 1 &mdash; Préparation</span>
                    <h3 class="h4 fw-bold mb-2 results-heading">La prochaine élection est en préparation</h3>
                    <p class="results-text-muted mx-auto mb-4" style="max-width: 560px;">
                        Le scrutin <strong>« <?= SecurityHelper::e($currentElection['titre']) ?> »</strong> est actuellement en phase de préparation par le bureau du SAS. Les résultats officiels seront publiés sur cette page à l'issue du vote.
                    </p>
                    <div>
                        <a href="index.php?page=home" class="btn btn-primary px-4">
                            <i class="bi bi-house me-1"></i> Retour à l'accueil
                        </a>
                    </div>
                </div>

            <?php elseif ($publicCase === 2): ?>
                <!-- CAS 2 : Inscription ou candidature -->
                <div class="results-card p-4 p-md-5 text-center rs-reveal" style="--d:.2s">
                    <div class="rounded-circle bg-info-subtle text-info d-inline-flex align-items-center justify-content-center mx-auto mb-3 rs-state-icon">
                        <i class="bi bi-person-lines-fill fs-1"></i>
                    </div>
                    <span class="badge bg-<?= SecurityHelper::e($phaseInfo['badge'] ?? 'info') ?> px-3 py-2 mx-auto mb-2 d-table">
                        Phase en cours : <?= SecurityHelper::e($phaseInfo['label'] ?? 'Inscriptions / Candidatures') ?>
                    </span>
                    <h3 class="h4 fw-bold mb-2 results-heading"><?= SecurityHelper::e($phaseInfo['label'] ?? 'Période pré-électorale en cours') ?></h3>
                    <p class="results-text-muted mx-auto mb-4" style="max-width: 600px;">
                        Le scrutin <strong>« <?= SecurityHelper::e($currentElection['titre']) ?> »</strong> est actuellement dans sa phase préparatoire (<strong><?= SecurityHelper::e($phaseInfo['label'] ?? '') ?></strong>). Le vote n'a pas encore débuté et aucun résultat n'est disponible pour le moment.
                    </p>

                    <div class="row g-3 justify-content-center mb-4 text-start" style="max-width: 650px; margin: 0 auto;">
                        <?php if (!empty($currentElection['date_ouverture_inscriptions']) && !empty($currentElection['date_fermeture_inscriptions'])): ?>
                            <div class="col-12 col-md-6">
                                <div class="kpi-card-res p-3 text-start h-100">
                                    <div class="fw-bold small results-heading mb-1">
                                        <i class="bi bi-person-plus text-success me-1"></i> Inscriptions des membres
                                    </div>
                                    <div class="small results-text-muted">
                                        Du <?= date('d/m/Y à H:i', strtotime($currentElection['date_ouverture_inscriptions'])) ?><br>
                                        au <?= date('d/m/Y à H:i', strtotime($currentElection['date_fermeture_inscriptions'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($currentElection['date_ouverture_candidatures']) && !empty($currentElection['date_fermeture_candidatures'])): ?>
                            <div class="col-12 col-md-6">
                                <div class="kpi-card-res p-3 text-start h-100">
                                    <div class="fw-bold small results-heading mb-1">
                                        <i class="bi bi-person-badge text-primary me-1"></i> Dépôt des candidatures
                                    </div>
                                    <div class="small results-text-muted">
                                        Du <?= date('d/m/Y à H:i', strtotime($currentElection['date_ouverture_candidatures'])) ?><br>
                                        au <?= date('d/m/Y à H:i', strtotime($currentElection['date_fermeture_candidatures'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <?php if (!empty($phaseInfo['ins_open'])): ?>
                            <a href="index.php?page=inscription" class="btn btn-success px-4">
                                <i class="bi bi-person-plus me-1"></i> S'inscrire comme membre
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($phaseInfo['cand_open'])): ?>
                            <a href="index.php?page=candidater" class="btn btn-primary px-4">
                                <i class="bi bi-person-badge me-1"></i> Déposer une candidature
                            </a>
                        <?php endif; ?>
                        <a href="index.php?page=home" class="btn btn-outline-secondary px-4">
                            <i class="bi bi-house me-1"></i> Accueil
                        </a>
                    </div>
                </div>

            <?php elseif ($publicCase === 3): ?>
                <!-- CAS 3 : Campagne électorale -->
                <div class="results-card p-4 p-md-5 text-center rs-reveal" style="--d:.2s">
                    <div class="rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center mx-auto mb-3 rs-state-icon">
                        <i class="bi bi-megaphone-fill fs-1"></i>
                    </div>
                    <span class="badge bg-warning text-dark px-3 py-2 mx-auto mb-2 d-table">Phase 4 &mdash; Campagne électorale</span>
                    <h3 class="h4 fw-bold mb-2 results-heading">La campagne électorale est en cours</h3>
                    <p class="results-text-muted mx-auto mb-3" style="max-width: 580px;">
                        La campagne électorale pour <strong>« <?= SecurityHelper::e($currentElection['titre']) ?> »</strong> est actuellement ouverte. Les candidats validés présentent leur vision aux membres avant l'ouverture du vote.
                    </p>
                    <?php if (!empty($currentElection['date_debut_campagne']) && !empty($currentElection['date_fin_campagne'])): ?>
                        <p class="small results-text-muted mb-4">
                            Campagne du <strong><?= date('d/m/Y à H:i', strtotime($currentElection['date_debut_campagne'])) ?></strong>
                            au <strong><?= date('d/m/Y à H:i', strtotime($currentElection['date_fin_campagne'])) ?></strong>
                            &bull; Ouverture du vote le <strong><?= date('d/m/Y à H:i', strtotime($currentElection['date_debut_vote'] ?? $currentElection['date_debut'])) ?></strong>.
                        </p>
                    <?php endif; ?>
                    <div>
                        <a href="index.php?page=home" class="btn btn-primary px-4">
                            <i class="bi bi-house me-1"></i> Retour à l'accueil
                        </a>
                    </div>
                </div>

            <?php elseif ($publicCase === 4): ?>
                <!-- CAS 4 : Vote en cours (confidentialité absolue des résultats en direct) -->
                <?php $finVoteTs = strtotime($currentElection['date_fin_vote'] ?? $currentElection['date_fin']); ?>
                <div class="results-card p-4 p-md-5 text-center rs-reveal" style="--d:.2s">
                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mx-auto mb-3 rs-state-icon">
                        <i class="bi bi-shield-lock-fill fs-1"></i>
                    </div>
                    <span class="badge bg-success px-3 py-2 mx-auto mb-2 d-table">Phase 5 &mdash; Vote en cours</span>
                    <h3 class="h4 fw-bold mb-2 results-heading">Le vote est ouvert &mdash; Résultats non encore publics</h3>
                    <p class="results-text-muted mx-auto mb-4" style="max-width: 580px;">
                        Conformément aux règles de transparence et d'équité du SAS, les résultats de l'élection
                        <strong>« <?= SecurityHelper::e($currentElection['titre']) ?> »</strong> ne sont pas publics pendant le déroulement du vote et seront publiés automatiquement dès la clôture officielle du scrutin
                        (prévue le <strong><?= date('d/m/Y à H:i', $finVoteTs) ?></strong>).
                    </p>

                    <?php if ($finVoteTs): ?>
                        <div class="rs-countdown" data-end="<?= (int)$finVoteTs * 1000 ?>">
                            <div class="rs-cd-box"><b data-u="d">00</b><span>Jours</span></div>
                            <div class="rs-cd-box"><b data-u="h">00</b><span>Heures</span></div>
                            <div class="rs-cd-box"><b data-u="m">00</b><span>Min</span></div>
                            <div class="rs-cd-box"><b data-u="s">00</b><span>Sec</span></div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <a href="index.php?page=voter" class="btn btn-primary px-4 fw-bold">
                            <i class="bi bi-fingerprint me-1"></i> Participer au vote
                        </a>
                        <a href="index.php?page=home" class="btn btn-outline-secondary px-4">
                            <i class="bi bi-house me-1"></i> Accueil
                        </a>
                    </div>
                </div>

            <?php elseif ($isCloturee && $donnees): ?>
                <!-- CAS 5 : Le dernier scrutin (ou tour consulté) est clôturé -> Affichage complet des résultats -->

                <!-- Sélecteur de tour si plusieurs tours existent dans ce scrutin (Section 10) -->
                <?php if (!empty($donnees['tours']) && count($donnees['tours']) > 1): ?>
                    <div class="results-card p-3 mb-4 rs-reveal" style="--d:.12s">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="small fw-bold results-heading">
                                <i class="bi bi-layers-fill text-primary me-1"></i> Consulter les résultats par tour de scrutin :
                            </span>
                            <div class="tour-switch">
                                <?php foreach ($donnees['tours'] as $tItem): ?>
                                    <?php
                                    $isSelectedTour = (!empty($donnees['tour']) && (int)$donnees['tour']['id'] === (int)$tItem['id']);
                                    $tStatutBadge = ($tItem['statut'] === 'cloturee') ? 'Clôturé' : (($tItem['statut'] === 'ouverte') ? 'En cours' : 'En préparation');
                                    ?>
                                    <a href="index.php?page=resultats&tour_id=<?= (int)$tItem['id'] ?>"
                                        class="btn btn-sm <?= $isSelectedTour ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                                        <i class="bi bi-flag-fill me-1"></i>
                                        <?= SecurityHelper::e($tItem['libelle'] ?? ('Tour ' . $tItem['numero_tour'])) ?>
                                        <span class="badge <?= $isSelectedTour ? 'bg-light text-primary' : 'bg-secondary-subtle text-secondary' ?> ms-1">
                                            <?= $tStatutBadge ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Bandeau d'état de certification / proclamation et informations du tour -->
                <div class="alert <?= !empty($currentElection['resultats_certifies']) ? 'alert-success border-success-subtle' : 'alert-primary border-primary-subtle' ?> d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 rs-reveal" style="--d:.15s">
                    <div>
                        <?php if (!empty($donnees['tour'])): ?>
                            <span class="badge bg-primary me-2">
                                <?= SecurityHelper::e($donnees['tour']['libelle'] ?? ('Tour ' . $donnees['tour']['numero_tour'])) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($currentElection['resultats_certifies'])): ?>
                            <i class="bi bi-patch-check-fill me-1"></i>
                            <strong>Résultats définitifs certifiés par le bureau du SAS</strong>
                            <?php if (!empty($currentElection['date_certification'])): ?>
                                <span class="small ms-1">(certifiés le <?= date('d/m/Y à H:i', strtotime($currentElection['date_certification'])) ?>)</span>
                            <?php endif; ?>
                        <?php elseif (!empty($currentElection['etat_egalite_persistante'])): ?>
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>Égalité persistante &mdash; aucun vainqueur n’est proclamé</strong>
                        <?php else: ?>
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong>Scrutin clôturé &mdash; Résultats publiés</strong>
                            <?php
                            $dateClotureAffichee = $donnees['tour']['date_cloture_effective']
                                ?? $currentElection['date_cloture_effective']
                                ?? ($currentElection['date_fin_vote'] ?? $currentElection['date_fin']);
                            ?>
                            <span class="small ms-1">(clôture effective le <?= date('d/m/Y à H:i', strtotime($dateClotureAffichee)) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-dark">Fuseau : Africa/Abidjan</span>
                </div>

                <!-- Proclamation officielle : Égalité (Section 8 - Orange) ou Victoire (Section 9 - Vert) -->
                <?php if ($donnees['total_bulletins'] > 0): ?>
                    <?php if ($donnees['is_egalite']): ?>
                        <!-- SECTION 8 : CAS D'ÉGALITÉ (ORANGE, AUCUNE COUPE, AUCUN VAINQUEUR, MESSAGE COMITÉ AD HOC) -->
                        <div class="equality-comite-banner p-4 p-md-5 mb-4 text-center rs-reveal" style="--d:.2s">
                            <div class="mb-2">
                                <span class="badge bg-warning text-dark px-3 py-2 fw-bold text-uppercase">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    <?= !empty($donnees['etat_egalite_persistante']) ? 'Égalité persistante après le nombre maximal de tours' : 'Égalité en tête du tour (' . $donnees['max_voix'] . ' voix)' ?>
                                </span>
                            </div>
                            <h2 class="h4 fw-bold mb-3">
                                Aucun vainqueur proclamé sur ce tour &mdash; Ex æquo au 1er rang (<?= $donnees['max_voix'] ?> voix)
                            </h2>
                            <p class="mb-3" style="color: #7c2d12;">
                                Candidats arrivés en tête à égalité :
                                <strong>
                                    <?= implode(', ', array_map(fn($l) => SecurityHelper::e($l['prenoms'] . ' ' . $l['nom']), $donnees['leaders'])) ?>
                                </strong>
                            </p>
                            <div class="equality-comite-instruction">
                                <i class="bi bi-megaphone-fill me-2"></i>ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !
                            </div>
                            <?php if (!empty($donnees['qualifies']) && empty($donnees['etat_egalite_persistante'])): ?>
                                <div class="mt-3 small fw-semibold" style="color: #9a3412;">
                                    <i class="bi bi-arrow-right-circle-fill me-1"></i>
                                    Candidats qualifiés d'office pour le tour suivant :
                                    <strong><?= implode(', ', array_map(fn($q) => SecurityHelper::e($q['prenoms'] . ' ' . $q['nom']), $donnees['qualifies'])) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($donnees['vainqueur']): ?>
                        <!-- SECTION 9 : CAS DE VICTOIRE (VERT, COUPE SUR LE VAINQUEUR, PHRASE OFFICIELLE EXACTE) -->
                        <?php $v = $donnees['vainqueur']; ?>
                        <div class="winner-proclamation-banner winner-green-banner p-4 p-md-5 mb-4 text-center rs-reveal" style="--d:.2s">
                            <canvas id="rsConfetti"></canvas>
                            <div class="position-relative" style="z-index: 2;">
                                <div class="mb-3">
                                    <span class="winner-crown-badge">
                                        <i class="bi bi-trophy-fill"></i> Président(e) Élu(e) &bull; Vainqueur Officiel (#1)
                                    </span>
                                </div>
                                <h2 class="h2 fw-bold text-white mb-2"><?= SecurityHelper::e($v['prenoms'] . ' ' . $v['nom']) ?></h2>
                                <div class="winner-official-sentence-box">
                                    Les votes du scrutin "<?= SecurityHelper::e($donnees['election']['titre']) ?>" : vainqueur <?= SecurityHelper::e($v['prenoms'] . ' ' . $v['nom']) ?> avec <?= (int)$v['nb_voix'] ?> voix (<?= number_format((float)$v['pourcentage'], 1, ',', ' ') ?> %).
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Statistiques clés de participation (KPIs) -->
                <section class="kpi-section">
                    <div class="row g-3 text-center">
                        <div class="col-6 col-md-3 rs-reveal" style="--d:.1s">
                            <div class="kpi-card-res h-100">
                                <div class="kpi-icon-wrap bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div>
                                <div class="kpi-val" data-count="<?= (int)$donnees['nb_eligibles'] ?>"><?= $donnees['nb_eligibles'] ?></div>
                                <div class="kpi-lbl">Électeurs autorisés</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 rs-reveal" style="--d:.2s">
                            <div class="kpi-card-res h-100">
                                <div class="kpi-icon-wrap bg-info-subtle text-info"><i class="bi bi-person-check-fill"></i></div>
                                <div class="kpi-val" data-count="<?= (int)$donnees['nb_votants'] ?>"><?= $donnees['nb_votants'] ?></div>
                                <div class="kpi-lbl">Votants</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 rs-reveal" style="--d:.3s">
                            <div class="kpi-card-res h-100">
                                <div class="kpi-icon-wrap bg-success-subtle text-success"><i class="bi bi-envelope-paper-fill"></i></div>
                                <div class="kpi-val" data-count="<?= (int)$donnees['total_bulletins'] ?>"><?= $donnees['total_bulletins'] ?></div>
                                <div class="kpi-lbl">Suffrages exprimés</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 rs-reveal" style="--d:.4s">
                            <div class="kpi-card-res h-100">
                                <div class="kpi-ring" style="--p: <?= number_format(min(100.0, max(0.0, (float)$donnees['taux_participation'])), 1, '.', '') ?>;">
                                    <svg viewBox="0 0 36 36" aria-hidden="true">
                                        <circle class="bg" cx="18" cy="18" r="15.9155" pathLength="100" />
                                        <circle class="fg" cx="18" cy="18" r="15.9155" pathLength="100" />
                                    </svg>
                                    <i class="bi bi-pie-chart-fill"></i>
                                </div>
                                <div class="kpi-val" data-count="<?= (float)$donnees['taux_participation'] ?>" data-decimals="1" data-suffix=" %"><?= number_format($donnees['taux_participation'], 1, ',', ' ') ?> %</div>
                                <div class="kpi-lbl">Participation</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Le Podium Officiel (Section 8 : orange sans coupe en cas d'égalité / Section 9 : vert avec coupe en cas de victoire) -->
                <?php if (!empty($donnees['podium'])): ?>
                    <?php
                    $podiumCount = count($donnees['podium']);
                    $containerMod = $podiumCount === 1 ? 'podium-single' : ($podiumCount === 2 ? 'podium-dual' : '');
                    ?>
                    <section class="podium-container <?= $containerMod ?>">
                        <?php foreach ($donnees['podium'] as $index => $p): ?>
                            <?php
                            $tierClass = match ($index) {
                                0 => 'podium-gold',
                                1 => 'podium-silver',
                                default => 'podium-bronze',
                            };
                            if ($donnees['is_egalite'] && !empty($p['is_leader'])) {
                                $tierClass .= ' podium-equality-orange';
                                $orderStyle = 'order: ' . ($index + 1) . ';';
                            } else {
                                if (!$donnees['is_egalite'] && $index === 0 && $p['nb_voix'] > 0) {
                                    $tierClass .= ' podium-winner-green';
                                }
                                $orderStyle = match ($index) {
                                    0 => 'order: 2;',
                                    1 => 'order: 1;',
                                    default => 'order: 3;',
                                };
                            }
                            $delay = match ($index) {
                                0 => '.45s',
                                1 => '.15s',
                                default => '.3s'
                            };
                            ?>
                            <article class="podium-card <?= $tierClass ?>" style="<?= $orderStyle ?> --d: <?= $delay ?>;">
                                <?php if (!$donnees['is_egalite'] && $index === 0 && $p['nb_voix'] > 0): ?>
                                    <div class="podium-crown-badge" title="1ère place - Vainqueur">
                                        <i class="bi bi-trophy-fill"></i>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <div class="podium-avatar-wrap">
                                        <?php if (!empty($p['photo']) && file_exists(__DIR__ . '/../../uploads/candidats/' . $p['photo'])): ?>
                                            <img src="uploads/candidats/<?= SecurityHelper::e($p['photo']) ?>" alt="Photo" class="podium-avatar">
                                        <?php else: ?>
                                            <div class="podium-avatar-initials">
                                                <?= strtoupper(substr($p['nom'], 0, 1) . substr($p['prenoms'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="podium-rank-badge"><?= SecurityHelper::e($p['rang_label']) ?></span>
                                    </div>

                                    <h3 class="podium-name"><?= SecurityHelper::e($p['prenoms'] . ' ' . $p['nom']) ?></h3>
                                </div>

                                <div class="podium-score-box">
                                    <div class="podium-votes-count" data-count="<?= (int)$p['nb_voix'] ?>"><?= $p['nb_voix'] ?></div>
                                    <div class="small results-text-muted">voix obtenues</div>
                                    <div class="podium-percent-badge">
                                        <?= number_format($p['pourcentage'], 1, ',', ' ') ?> %
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <!-- Suite du classement et récapitulatif complet -->
                <section class="ranking-section mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 rs-reveal">
                        <h3 class="h6 fw-bold mb-0 results-heading">
                            <i class="bi bi-list-ol text-primary me-2"></i>Classement Complet des Candidats
                        </h3>
                        <span class="results-text-muted small"><?= count($donnees['candidats']) ?> candidat(s) participant(s)</span>
                    </div>

                    <?php if (empty($donnees['candidats'])): ?>
                        <p class="results-text-muted text-center mb-0">Aucun candidat validé pour ce tour.</p>
                    <?php else: ?>
                        <?php foreach ($donnees['candidats'] as $i => $c): ?>
                            <?php
                            $isTiedLeader = ($donnees['is_egalite'] && !empty($c['is_leader']));
                            $isSingleLeader = (!$donnees['is_egalite'] && !empty($c['is_leader']) && $c['nb_voix'] > 0);
                            $cardExtraClass = $isTiedLeader ? 'ranking-equality-orange' : ($isSingleLeader ? 'ranking-winner-green' : '');
                            $barExtraClass  = $isTiedLeader ? 'bar-equality-orange' : ($isSingleLeader ? 'bar-winner-green' : '');
                            $rowDelay = min($i, 8) * 0.08;
                            ?>
                            <div class="ranking-card-item rs-reveal <?= $cardExtraClass ?>" style="--d: <?= $rowDelay ?>s">
                                <div class="ranking-rank-badge">
                                    <?= SecurityHelper::e($c['rang_label']) ?>
                                </div>

                                <?php if (!empty($c['photo']) && file_exists(__DIR__ . '/../../uploads/candidats/' . $c['photo'])): ?>
                                    <img src="uploads/candidats/<?= SecurityHelper::e($c['photo']) ?>" alt="Photo" class="ranking-avatar">
                                <?php else: ?>
                                    <div class="ranking-avatar-initials">
                                        <?= strtoupper(substr($c['nom'], 0, 1) . substr($c['prenoms'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <span class="fw-bold results-heading">
                                            <?= SecurityHelper::e($c['prenoms'] . ' ' . $c['nom']) ?>
                                            <?php if ($isSingleLeader): ?>
                                                <i class="bi bi-trophy-fill text-success ms-1" title="Vainqueur (#1)"></i>
                                            <?php elseif ($isTiedLeader): ?>
                                                <span class="badge bg-warning text-dark ms-1">Ex æquo</span>
                                            <?php endif; ?>
                                        </span>
                                        <div>
                                            <span class="fw-bold results-heading"><?= $c['nb_voix'] ?> voix</span>
                                            <span class="badge <?= $isTiedLeader ? 'bg-warning text-dark' : ($isSingleLeader ? 'bg-success' : 'bg-primary') ?> ms-2">
                                                <?= number_format($c['pourcentage'], 1, ',', ' ') ?> %
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ranking-bar-track">
                                        <div class="ranking-bar-fill <?= $barExtraClass ?>" style="--w: <?= number_format(min(100.0, max(0.0, (float)$c['pourcentage'])), 1, '.', '') ?>%; --d: <?= .25 + $rowDelay ?>s;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- Décisions officielles du bureau (si enregistrées, ex. après égalité persistante) -->
                <?php if (!empty($donnees['decisions_bureau'])): ?>
                    <section class="results-card p-4 mb-4 border-start border-primary border-4 rs-reveal">
                        <h3 class="h6 fw-bold mb-3 results-heading">
                            <i class="bi bi-journal-check text-primary me-2"></i>Décisions Officielles du Bureau Électoral
                        </h3>
                        <?php foreach ($donnees['decisions_bureau'] as $dec): ?>
                            <div class="decision-item">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                                    <span class="badge bg-primary">Décision du bureau</span>
                                    <small class="text-muted">Enregistrée le <?= date('d/m/Y à H:i', strtotime($dec['date_decision'])) ?></small>
                                </div>
                                <p class="fw-bold mb-1"><?= SecurityHelper::e($dec['decision']) ?></p>
                                <p class="small text-muted mb-0">Motif officiel : <?= SecurityHelper::e($dec['motif']) ?></p>
                                <?php if (!empty($dec['candidat_nom'])): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-success">
                                            <i class="bi bi-trophy-fill me-1"></i> Candidat retenu : <?= SecurityHelper::e($dec['candidat_prenoms'] . ' ' . $dec['candidat_nom']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <!-- Historique complet de tous les tours du scrutin (Section 10) -->
                <?php if (!empty($donnees['tours_history']) && count($donnees['tours_history']) > 1): ?>
                    <section class="results-card p-4 rs-reveal">
                        <h3 class="h6 fw-bold mb-3 results-heading">
                            <i class="bi bi-clock-history text-primary me-2"></i>Historique Complet des Tours du Scrutin
                        </h3>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Tour</th>
                                        <th>Période de vote</th>
                                        <th>Électeurs / Votants</th>
                                        <th>Participation</th>
                                        <th>Issue du tour</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($donnees['tours_history'] as $th): ?>
                                        <?php $tr = $th['resultats'] ?? $th;
                                        $tInfo = $th['tour']; ?>
                                        <tr>
                                            <td class="fw-bold"><?= SecurityHelper::e($tInfo['libelle'] ?? ('Tour ' . $tInfo['numero_tour'])) ?></td>
                                            <td class="small">
                                                Du <?= date('d/m/Y H:i', strtotime($tInfo['date_debut_vote'])) ?><br>
                                                au <?= date('d/m/Y H:i', strtotime($tInfo['date_fin_vote'])) ?>
                                            </td>
                                            <td class="small"><?= $tr['nb_votants'] ?> / <?= $tr['nb_eligibles'] ?></td>
                                            <td><span class="badge bg-info-subtle text-info"><?= number_format($tr['taux_participation'], 1, ',', ' ') ?> %</span></td>
                                            <td>
                                                <?php if ($tr['is_egalite']): ?>
                                                    <span class="badge bg-warning text-dark">Égalité en tête (<?= $tr['max_voix'] ?> voix)</span>
                                                <?php elseif (!empty($tr['vainqueur'])): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-trophy-fill me-1"></i><?= SecurityHelper::e($tr['vainqueur']['prenoms'] . ' ' . $tr['vainqueur']['nom']) ?> (<?= $tr['vainqueur']['nb_voix'] ?> voix)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Aucun vote</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="index.php?page=resultats&tour_id=<?= (int)$tInfo['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    Voir détails
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>

                <p class="rs-footer">
                    <i class="bi bi-shield-check me-1"></i>
                    Document officiel &mdash; édité le <?= date('d/m/Y à H:i') ?> (fuseau Africa/Abidjan)
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        (function() {
            'use strict';
            var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            /* --- Compteur animé --- */
            function countUp(el) {
                var target = parseFloat(el.dataset.count);
                if (isNaN(target) || reduce) return;
                var dec = parseInt(el.dataset.decimals || '0', 10),
                    suffix = el.dataset.suffix || '';
                var dur = 1700,
                    start = null;

                function fmt(n) {
                    return n.toLocaleString('fr-FR', {
                        minimumFractionDigits: dec,
                        maximumFractionDigits: dec
                    }) + suffix;
                }

                function step(ts) {
                    if (!start) start = ts;
                    var p = Math.min((ts - start) / dur, 1);
                    el.textContent = fmt(target * (1 - Math.pow(1 - p, 4)));
                    if (p < 1) requestAnimationFrame(step);
                    else el.textContent = fmt(target);
                }
                requestAnimationFrame(step);
            }

            /* --- Révélation au scroll --- */
            var targets = document.querySelectorAll('.rs-reveal, .podium-card');
            if (!('IntersectionObserver' in window)) {
                targets.forEach(function(t) {
                    t.classList.add('in');
                    t.querySelectorAll('[data-count]').forEach(countUp);
                });
            } else {
                var io = new IntersectionObserver(function(entries) {
                    entries.forEach(function(e) {
                        if (!e.isIntersecting) return;
                        var t = e.target;
                        t.classList.add('in');
                        t.querySelectorAll('[data-count]').forEach(countUp);
                        io.unobserve(t);
                    });
                }, {
                    threshold: .15
                });
                targets.forEach(function(t) {
                    io.observe(t);
                });
            }

            /* --- Compte à rebours --- */
            var cd = document.querySelector('.rs-countdown');
            if (cd) {
                var end = parseInt(cd.dataset.end, 10),
                    els = {};
                cd.querySelectorAll('[data-u]').forEach(function(b) {
                    els[b.dataset.u] = b;
                });
                var pad = function(n) {
                    return String(n).padStart(2, '0');
                };
                var timer = setInterval(tick, 1000);

                function tick() {
                    var s = Math.floor(Math.max(0, end - Date.now()) / 1000);
                    els.d.textContent = pad(Math.floor(s / 86400));
                    els.h.textContent = pad(Math.floor(s % 86400 / 3600));
                    els.m.textContent = pad(Math.floor(s % 3600 / 60));
                    els.s.textContent = pad(s % 60);
                    if (s <= 0) {
                        clearInterval(timer);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                }
                tick();
            }

            /* --- Confettis (uniquement en cas de victoire) --- */
            var cv = document.getElementById('rsConfetti');
            if (cv && !reduce) {
                var ctx = cv.getContext('2d'),
                    W, H, parts = [],
                    running = false;
                var colors = ['#f5b301', '#ffe27a', '#ffffff', '#6ee7b7', '#34d399', '#a7f3d0'];

                function resize() {
                    W = cv.width = cv.offsetWidth;
                    H = cv.height = cv.offsetHeight;
                }

                function spawn(n) {
                    for (var i = 0; i < n; i++) parts.push({
                        x: Math.random() * W,
                        y: -10 - Math.random() * H * .5,
                        s: 5 + Math.random() * 7,
                        vx: -1 + Math.random() * 2,
                        vy: 1.5 + Math.random() * 3,
                        r: Math.random() * 6,
                        vr: -.2 + Math.random() * .4,
                        c: colors[Math.floor(Math.random() * colors.length)]
                    });
                }

                function draw() {
                    ctx.clearRect(0, 0, W, H);
                    parts.forEach(function(p) {
                        p.x += p.vx;
                        p.y += p.vy;
                        p.r += p.vr;
                        ctx.save();
                        ctx.translate(p.x, p.y);
                        ctx.rotate(p.r);
                        ctx.fillStyle = p.c;
                        ctx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2);
                        ctx.restore();
                    });
                    parts = parts.filter(function(p) {
                        return p.y < H + 20;
                    });
                    if (parts.length) requestAnimationFrame(draw);
                    else {
                        running = false;
                        ctx.clearRect(0, 0, W, H);
                    }
                }

                function fire() {
                    if (running) return;
                    running = true;
                    resize();
                    spawn(140);
                    setTimeout(function() {
                        spawn(80);
                    }, 700);
                    requestAnimationFrame(draw);
                }
                var banner = cv.parentElement;
                var cio = new IntersectionObserver(function(en) {
                    if (en[0].isIntersecting) {
                        setTimeout(fire, 500);
                        cio.disconnect();
                    }
                }, {
                    threshold: .4
                });
                cio.observe(banner);
                banner.addEventListener('click', fire);
                window.addEventListener('resize', resize);
            }
        })();
    </script>
</body>

</html>