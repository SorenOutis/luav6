    <style>
        [x-cloak] {
            display: none !important;
        }

        .welcome-logo,
        .welcome-greeting,
        .welcome-input,
        .welcome-suggestions {
            animation: welcome-enter 0.25s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        .welcome-greeting {
            animation-delay: 40ms;
        }

        .welcome-input {
            animation-delay: 80ms;
        }

        .welcome-suggestions {
            animation-delay: 120ms;
        }

        @keyframes welcome-enter {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes welcome-fade {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* â”€â”€ Thinking 3-dots animation â”€â”€ */
        .thinking-dot {
            display: inline-block;
            width: 4px;
            height: 4px;
            border-radius: 9999px;
            background-color: currentColor;
            animation: thinking-dot-bounce 1.3s infinite ease-in-out both;
        }
        .thinking-dot:nth-child(1) {
            animation-delay: -0.32s;
        }
        .thinking-dot:nth-child(2) {
            animation-delay: -0.16s;
        }
        .thinking-dot:nth-child(3) {
            animation-delay: 0s;
        }

        @keyframes thinking-dot-bounce {
            0%, 80%, 100% {
                transform: scale(0.6);
                opacity: 0.35;
            }
            40% {
                transform: scale(1.25);
                opacity: 1;
            }
        }

        /* â”€â”€ Geometric Fox (ChatAiOrb from Student Chat) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
        .wolf-persona {
            --wolf-inner: #ffffff;
        }
        .dark .wolf-persona {
            --wolf-inner: #18181b;
        }

        .wolf-head,
        .wolf-circle,
        .wolf-spark {
            transform-box: view-box;
            transform-origin: 60px 60px;
        }
        .wolf-ear-left {
            transform-origin: 35px 48px;
        }
        .wolf-ear-right {
            transform-origin: 85px 48px;
        }
        .wolf-muzzle {
            transform-origin: 60px 78px;
        }

        .wolf-circle,
        .wolf-spark {
            opacity: 0;
        }

        /* â”€â”€ Tri-form cycle: Fox -> Circle -> Spark -> Fox (3.6s) â”€â”€ */
        [data-motion='welcome'] .wolf-head {
            animation: wolf-to-circle 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='welcome'] .wolf-circle {
            animation: wolf-circle-reveal 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='welcome'] .wolf-spark {
            animation: wolf-spark-reveal 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='welcome'] .wolf-ear-left {
            animation: wolf-ear-fold-left 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='welcome'] .wolf-ear-right {
            animation: wolf-ear-fold-right 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='welcome'] .wolf-muzzle {
            animation: wolf-muzzle-retract 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }

        [data-motion='listening'] .wolf-ear-left {
            animation: wolf-listen-left 2.5s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='listening'] .wolf-ear-right {
            animation: wolf-listen-right 2.5s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='thinking'] .wolf-head {
            animation: wolf-think 2s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='speaking'] .wolf-muzzle {
            animation: wolf-speak 0.5s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }

        @keyframes wolf-to-circle {
            0%, 14%, 86%, 100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
            26%, 74% {
                transform: scale(0.2) rotate(30deg);
                opacity: 0;
            }
        }

        @keyframes wolf-circle-reveal {
            0%, 14%, 54%, 100% {
                transform: scale(0);
                opacity: 0;
            }
            26%, 42% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes wolf-spark-reveal {
            0%, 42%, 86%, 100% {
                transform: scale(0) rotate(-35deg);
                opacity: 0;
            }
            54%, 72% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        @keyframes wolf-ear-fold-left {
            0%, 14%, 86%, 100% {
                transform: rotate(0deg);
            }
            26%, 74% {
                transform: rotate(25deg) translate(6px, 8px);
            }
        }

        @keyframes wolf-ear-fold-right {
            0%, 14%, 86%, 100% {
                transform: rotate(0deg);
            }
            26%, 74% {
                transform: rotate(-25deg) translate(-6px, 8px);
            }
        }

        @keyframes wolf-muzzle-retract {
            0%, 14%, 86%, 100% {
                transform: translateY(0) scale(1);
            }
            26%, 74% {
                transform: translateY(-8px) scale(0.7);
            }
        }

        @keyframes wolf-listen-left {
            0%, 80%, 100% { transform: rotate(0deg); }
            40% { transform: rotate(-7deg); }
        }
        @keyframes wolf-listen-right {
            0%, 80%, 100% { transform: rotate(0deg); }
            40% { transform: rotate(7deg); }
        }
        @keyframes wolf-think {
            0%, 100% { transform: rotate(-3deg); opacity: 1; }
            50% { transform: rotate(3deg); opacity: 0.7; }
        }
        @keyframes wolf-speak {
            0%, 100% { transform: scaleY(1); }
            50% { transform: scaleY(0.92); }
        }

        @media (prefers-reduced-motion: reduce) {
            .welcome-logo,
            .welcome-greeting,
            .welcome-input,
            .welcome-suggestions {
                animation: welcome-fade 0.2s ease-out both;
            }
            .fox-float,
            .wolf-head,
            .wolf-circle,
            .wolf-spark,
            .wolf-ear-left,
            .wolf-ear-right,
            .wolf-muzzle {
                animation: none !important;
            }
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
