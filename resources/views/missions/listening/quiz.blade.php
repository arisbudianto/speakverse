<x-app-layout>

    <div id="listeningQuizPage">

        {{-- =========================================================
            HEADER QUIZ
        ========================================================== --}}
        <section class="listening-card quiz-header-card">
            <div class="quiz-header-decoration"></div>

            <div class="quiz-header-content">
                <div class="quiz-header-copy">
                    <div class="quiz-type-badge">
                        <span aria-hidden="true">🎧</span>
                        <span>Listening Quiz</span>
                    </div>

                    <h1 class="quiz-page-title">
                        {{ $lesson->title }} Quiz
                    </h1>

                    <p class="quiz-page-description">
                        Dengarkan audio dan jawab seluruh pertanyaan yang tersedia.
                    </p>
                </div>

                <div class="quiz-total-card">
                    <span class="quiz-total-label">
                        Total Soal
                    </span>

                    <strong class="quiz-total-value">
                        {{ $questions->count() }} Soal
                    </strong>
                </div>
            </div>
        </section>

        @if ($questions->count())

            {{-- =====================================================
                AUDIO DAN INSTRUKSI UTAMA
            ====================================================== --}}
            @if (
                isset($material) &&
                ($material->instruction || $material->audio_file)
            )
                <section class="listening-card main-audio-card">

                    @if ($material->instruction)
                        <div class="main-instruction">
                            <h2 class="main-instruction-title">
                                Instruction
                            </h2>

                            <p class="main-instruction-text">
                                {{ $material->instruction }}
                            </p>
                        </div>
                    @endif

                    @if ($material->audio_file)
                        <div class="main-audio-row">
                            <div class="audio-information">
                                <div class="audio-icon" aria-hidden="true">
                                    🎧
                                </div>

                                <div class="audio-copy">
                                    <h2 class="audio-title">
                                        Audio Material
                                    </h2>

                                    <p class="audio-description">
                                        Putar audio untuk menjawab soal
                                    </p>
                                </div>
                            </div>

                            <div class="audio-player-container">
                                <audio
                                    controls
                                    preload="metadata"
                                    class="quiz-audio-player"
                                >
                                    <source
                                        src="{{ asset('storage/' . $material->audio_file) }}"
                                    >

                                    Browser kamu tidak mendukung pemutar audio.
                                </audio>
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            {{-- =====================================================
                FORM QUIZ
            ====================================================== --}}
            <section id="quizCard">
                <form id="quizForm" novalidate>
                    @csrf

                    <div class="question-list">
                        @foreach ($questions as $index => $question)
                            <article
                                class="listening-card question-card question-item"
                                data-question-id="{{ $question->id }}"
                            >
                                {{-- HEADER SOAL --}}
                                <div class="question-header">
                                    <span class="question-number">
                                        Soal No. {{ $index + 1 }}
                                    </span>

                                    <span class="question-score">
                                        Poin: {{ $question->score }}
                                    </span>
                                </div>

                                {{-- INSTRUKSI PER SOAL --}}
                                @if ($question->instruction)
                                    <div class="question-instruction">
                                        <h3 class="question-instruction-title">
                                            Instruksi Soal
                                        </h3>

                                        <p class="question-instruction-text">
                                            {{ $question->instruction }}
                                        </p>
                                    </div>
                                @endif

                                {{-- AUDIO PER SOAL --}}
                                @if ($question->audio_file)
                                    <div class="question-audio-box">
                                        <div class="question-audio-information">
                                            <div
                                                class="question-audio-icon"
                                                aria-hidden="true"
                                            >
                                                🎧
                                            </div>

                                            <div>
                                                <h3 class="question-audio-title">
                                                    Audio Soal
                                                </h3>

                                                <p class="question-audio-label">
                                                    Nomor {{ $index + 1 }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="question-audio-player">
                                            <audio
                                                controls
                                                preload="metadata"
                                                class="quiz-audio-player"
                                            >
                                                <source
                                                    src="{{ asset('storage/' . $question->audio_file) }}"
                                                >

                                                Browser kamu tidak mendukung pemutar audio.
                                            </audio>
                                        </div>
                                    </div>
                                @endif

                                {{-- TEKS PERTANYAAN --}}
                                <h2 class="question-text">
                                    {{ $question->question }}
                                </h2>

                                {{-- PILIHAN JAWABAN --}}
                                <div
                                    class="answer-list"
                                    role="radiogroup"
                                    aria-label="Jawaban soal nomor {{ $index + 1 }}"
                                >
                                    @foreach (['A', 'B', 'C', 'D', 'E'] as $option)
                                        @php
                                            $field = 'option_' . strtolower($option);

                                            $inputId =
                                                'question_' .
                                                $question->id .
                                                '_option_' .
                                                strtolower($option);
                                        @endphp

                                        @if (!empty($question->$field))
                                            <label
                                                for="{{ $inputId }}"
                                                class="answer-option"
                                            >
                                                <input
                                                    id="{{ $inputId }}"
                                                    type="radio"
                                                    name="question_{{ $question->id }}"
                                                    value="{{ $option }}"
                                                    class="answer-radio"
                                                >

                                                <span class="answer-text">
                                                    {{ $question->$field }}
                                                </span>
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>

                    {{-- PESAN VALIDASI --}}
                    <div
                        id="quizValidationMessage"
                        class="quiz-validation-message is-hidden"
                        role="alert"
                    >
                        <div class="validation-icon" aria-hidden="true">
                            !
                        </div>

                        <div>
                            <strong>Jawaban belum lengkap</strong>

                            <p id="quizValidationText">
                                Silakan jawab seluruh soal terlebih dahulu.
                            </p>
                        </div>
                    </div>

                    {{-- =================================================
                        TOMBOL SUBMIT
                    ================================================== --}}
                    <div id="listeningQuizSubmitArea">
                        <button
                            id="listeningQuizSubmitButton"
                            type="button"
                            style="
                                background-color: #059669 !important;
                                background-image: linear-gradient(
                                    135deg,
                                    #10b981 0%,
                                    #047857 100%
                                ) !important;
                                color: #ffffff !important;
                                border-color: #047857 !important;
                                opacity: 1 !important;
                            "
                        >
                            <svg
                                id="submitButtonIcon"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.25"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M5 12l4 4L19 6"></path>
                            </svg>

                            <span id="submitBtnText">
                                Submit Semua Jawaban
                            </span>
                        </button>
                    </div>
                </form>
            </section>

            {{-- =====================================================
                HASIL QUIZ
            ====================================================== --}}
            <section id="resultSection" class="result-section is-hidden">
                <div class="listening-card result-card">
                    <div class="result-icon" aria-hidden="true">
                        🎉
                    </div>

                    <h2 class="result-title">
                        Quiz Selesai!
                    </h2>

                    <p class="result-description">
                        Jawaban kamu telah berhasil disimpan.
                    </p>

                    <div class="result-score-card">
                        <span class="result-score-label">
                            Total Skor Kamu
                        </span>

                        <strong id="finalScore" class="result-score-value">
                            0
                        </strong>
                    </div>
                </div>

                <div class="result-actions">
                    <a
                        href="{{ route('student.listening', $lesson) }}"
                        class="result-secondary-button"
                    >
                        Back to Listening
                    </a>

                    <a
                        href="{{ route('missions') }}"
                        class="result-primary-button"
                    >
                        Back to Missions
                    </a>
                </div>
            </section>

        @else
            {{-- =====================================================
                BELUM ADA SOAL
            ====================================================== --}}
            <section class="listening-card empty-state">
                <div class="empty-state-icon" aria-hidden="true">
                    ❓
                </div>

                <h2 class="empty-state-title">
                    No Questions Yet
                </h2>

                <p class="empty-state-description">
                    Admin belum menambahkan soal untuk listening ini.
                </p>
            </section>
        @endif

    </div>

    <style>
        /*
        |--------------------------------------------------------------------------
        | THEME VARIABLES
        |--------------------------------------------------------------------------
        */

        #listeningQuizPage {
            --page-text: #0f172a;
            --muted-text: #64748b;
            --soft-text: #94a3b8;

            --card-background: #ffffff;
            --card-border: #dbe4ef;
            --soft-background: #f8fafc;

            --option-background: #ffffff;
            --option-hover-background: #faf5ff;

            --purple-text: #7e22ce;
            --purple-background: #f3e8ff;
            --purple-border: #e9d5ff;

            display: flex;
            flex-direction: column;
            gap: 24px;

            width: 100%;
            color: var(--page-text);
        }

        html.dark #listeningQuizPage,
        .dark #listeningQuizPage {
            --page-text: #f8fafc;
            --muted-text: #a7b2c5;
            --soft-text: #7f8ba1;

            --card-background: #0f172a;
            --card-border: #2a3950;
            --soft-background: #172033;

            --option-background: #1e293b;
            --option-hover-background: #273449;

            --purple-text: #d8b4fe;
            --purple-background: rgba(168, 85, 247, 0.14);
            --purple-border: rgba(192, 132, 252, 0.3);
        }

        /*
        |--------------------------------------------------------------------------
        | RESET KHUSUS HALAMAN QUIZ
        |--------------------------------------------------------------------------
        */

        #listeningQuizPage *,
        #listeningQuizPage *::before,
        #listeningQuizPage *::after {
            box-sizing: border-box;
        }

        #listeningQuizPage button,
        #listeningQuizPage input,
        #listeningQuizPage audio {
            font: inherit;
        }

        #listeningQuizPage .is-hidden {
            display: none !important;
        }

        /*
        |--------------------------------------------------------------------------
        | CARD
        |--------------------------------------------------------------------------
        */

        #listeningQuizPage .listening-card {
            border: 1px solid var(--card-border) !important;
            border-radius: 28px;

            background-color: var(--card-background) !important;
            color: var(--page-text) !important;

            box-shadow:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 12px 30px rgba(15, 23, 42, 0.035);
        }

        html.dark #listeningQuizPage .listening-card,
        .dark #listeningQuizPage .listening-card {
            box-shadow:
                0 1px 2px rgba(0, 0, 0, 0.2),
                0 16px 36px rgba(0, 0, 0, 0.12);
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        #listeningQuizPage .quiz-header-card {
            position: relative;
            overflow: hidden;
            padding: 34px;
        }

        #listeningQuizPage .quiz-header-decoration {
            position: absolute;
            top: -100px;
            right: -90px;

            width: 270px;
            height: 270px;

            border-radius: 9999px;
            background: rgba(168, 85, 247, 0.12);

            filter: blur(60px);
            pointer-events: none;
        }

        #listeningQuizPage .quiz-header-content {
            position: relative;
            z-index: 1;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
        }

        #listeningQuizPage .quiz-header-copy {
            min-width: 0;
        }

        #listeningQuizPage .quiz-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            margin-bottom: 16px;
            padding: 8px 14px;

            border: 1px solid var(--purple-border);
            border-radius: 9999px;

            background-color: var(--purple-background) !important;
            color: var(--purple-text) !important;

            font-size: 14px;
            font-weight: 800;
            line-height: 1;
        }

        #listeningQuizPage .quiz-page-title {
            margin: 0;

            color: var(--page-text) !important;

            font-size: clamp(28px, 4vw, 42px);
            font-weight: 900;
            line-height: 1.15;
        }

        #listeningQuizPage .quiz-page-description {
            max-width: 700px;
            margin: 12px 0 0;

            color: var(--muted-text) !important;

            font-size: 15px;
            line-height: 1.7;
        }

        #listeningQuizPage .quiz-total-card {
            display: flex;
            flex-shrink: 0;
            flex-direction: column;
            gap: 5px;

            min-width: 150px;
            padding: 16px 20px;

            border: 1px solid var(--card-border);
            border-radius: 18px;

            background-color: var(--soft-background) !important;
        }

        #listeningQuizPage .quiz-total-label {
            color: var(--muted-text) !important;

            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        #listeningQuizPage .quiz-total-value {
            color: var(--purple-text) !important;

            font-size: 25px;
            font-weight: 900;
            line-height: 1.2;
        }

        /*
        |--------------------------------------------------------------------------
        | AUDIO UTAMA
        |--------------------------------------------------------------------------
        */

        #listeningQuizPage .main-audio-card {
            padding: 20px;
        }

        #listeningQuizPage .main-instruction {
            margin-bottom: 16px;
            padding: 14px 16px;

            border: 1px solid var(--purple-border);
            border-radius: 16px;

            background-color: var(--purple-background) !important;
        }

        #listeningQuizPage .main-instruction-title,
        #listeningQuizPage .question-instruction-title {
            margin: 0 0 5px;

            color: var(--purple-text) !important;

            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        #listeningQuizPage .main-instruction-text,
        #listeningQuizPage .question-instruction-text {
            margin: 0;

            color: var(--muted-text) !important;

            font-size: 14px;
            line-height: 1.65;
        }

        #listeningQuizPage .main-audio-row {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        #listeningQuizPage .audio-information {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            gap: 12px;
        }

        #listeningQuizPage .audio-icon,
        #listeningQuizPage .question-audio-icon {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;

            width: 46px;
            height: 46px;

            border-radius: 14px;

            background-color: var(--purple-background) !important;

            font-size: 21px;
        }

        #listeningQuizPage .audio-title {
            margin: 0;

            color: var(--page-text) !important;

            font-size: 15px;
            font-weight: 800;
            line-height: 1.3;
        }

        #listeningQuizPage .audio-description {
            margin: 3px 0 0;

            color: var(--muted-text) !important;

            font-size: 12px;
            line-height: 1.4;
        }

        #listeningQuizPage .audio-player-container {
            min-width: 0;
            flex: 1;
        }

        #listeningQuizPage .quiz-audio-player {
            display: block;
            width: 100%;
            height: 42px;

            color-scheme: light;
        }

        html.dark #listeningQuizPage .quiz-audio-player,
        .dark #listeningQuizPage .quiz-audio-player {
            color-scheme: dark;
        }

        /*
        |--------------------------------------------------------------------------
        | PERTANYAAN
        |--------------------------------------------------------------------------
        */

        #listeningQuizPage .question-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        #listeningQuizPage .question-card {
            padding: 26px;

            transition:
                border-color 180ms ease,
                box-shadow 180ms ease;
        }

        #listeningQuizPage .question-card.is-unanswered {
            border-color: #ef4444 !important;

            box-shadow:
                0 0 0 3px rgba(239, 68, 68, 0.1),
                0 12px 30px rgba(239, 68, 68, 0.05);
        }

        #listeningQuizPage .question-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;

            margin-bottom: 18px;
        }

        #listeningQuizPage .question-number {
            display: inline-flex;
            align-items: center;

            padding: 6px 13px;

            border: 1px solid var(--purple-border);
            border-radius: 9999px;

            background-color: var(--purple-background) !important;
            color: var(--purple-text) !important;

            font-size: 12px;
            font-weight: 900;
            line-height: 1;
        }

        #listeningQuizPage .question-score {
            color: var(--muted-text) !important;

            font-size: 12px;
            font-weight: 700;
        }

        #listeningQuizPage .question-instruction {
            margin-bottom: 16px;
            padding: 13px 15px;

            border: 1px solid var(--card-border);
            border-radius: 15px;

            background-color: var(--soft-background) !important;
        }

        #listeningQuizPage .question-audio-box {
            display: flex;
            align-items: center;
            gap: 18px;

            margin-bottom: 18px;
            padding: 14px;

            border: 1px solid var(--card-border);
            border-radius: 16px;

            background-color: var(--soft-background) !important;
        }

        #listeningQuizPage .question-audio-information {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            gap: 11px;
        }

        #listeningQuizPage .question-audio-title {
            margin: 0;

            color: var(--page-text) !important;

            font-size: 12px;
            font-weight: 800;
        }

        #listeningQuizPage .question-audio-label {
            margin: 2px 0 0;

            color: var(--muted-text) !important;

            font-size: 11px;
        }

        #listeningQuizPage .question-audio-player {
            min-width: 0;
            flex: 1;
        }

        #listeningQuizPage .question-text {
            margin: 0 0 18px;

            color: var(--page-text) !important;

            font-size: clamp(17px, 2vw, 21px);
            font-weight: 800;
            line-height: 1.55;
        }

        /*
        |--------------------------------------------------------------------------
        | PILIHAN JAWABAN
        |--------------------------------------------------------------------------
        */

        #listeningQuizPage .answer-list {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        #listeningQuizPage .answer-option {
            display: flex;
            align-items: center;
            gap: 13px;

            min-height: 54px;
            padding: 13px 17px;

            border: 1px solid var(--card-border) !important;
            border-radius: 14px;

            background-color: var(--option-background) !important;
            color: var(--page-text) !important;

            cursor: pointer;
            user-select: none;

            transition:
                border-color 160ms ease,
                background-color 160ms ease,
                box-shadow 160ms ease,
                transform 160ms ease;
        }

        #listeningQuizPage .answer-option:hover {
            border-color: #a855f7 !important;
            background-color: var(--option-hover-background) !important;

            transform: translateY(-1px);
        }

        #listeningQuizPage .answer-option.is-selected {
            border-color: #9333ea !important;
            background-color: #faf5ff !important;

            box-shadow:
                0 0 0 1px rgba(147, 51, 234, 0.1),
                0 6px 16px rgba(147, 51, 234, 0.07);
        }

        html.dark #listeningQuizPage .answer-option.is-selected,
        .dark #listeningQuizPage .answer-option.is-selected {
            border-color: #c084fc !important;
            background-color: rgba(126, 34, 206, 0.25) !important;

            box-shadow:
                0 0 0 1px rgba(192, 132, 252, 0.12);
        }

        #listeningQuizPage .answer-radio {
            width: 19px !important;
            height: 19px !important;
            min-width: 19px !important;
            flex-shrink: 0;

            margin: 0 !important;

            accent-color: #9333ea;
            cursor: pointer;
        }

        #listeningQuizPage .answer-text {
            min-width: 0;

            color: var(--page-text) !important;

            font-size: 15px;
            font-weight: 600;
            line-height: 1.55;
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDASI
        |--------------------------------------------------------------------------
        */

        #listeningQuizPage .quiz-validation-message {
            display: flex;
            align-items: flex-start;
            gap: 12px;

            margin-top: 18px;
            padding: 14px 16px;

            border: 1px solid #fecaca;
            border-radius: 14px;

            background-color: #fef2f2 !important;
            color: #991b1b !important;
        }

        html.dark #listeningQuizPage .quiz-validation-message,
        .dark #listeningQuizPage .quiz-validation-message {
            border-color: rgba(248, 113, 113, 0.35);

            background-color: rgba(127, 29, 29, 0.2) !important;
            color: #fecaca !important;
        }

        #listeningQuizPage .validation-icon {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;

            width: 24px;
            height: 24px;

            border-radius: 9999px;

            background-color: #dc2626 !important;
            color: #ffffff !important;

            font-size: 13px;
            font-weight: 900;
        }

        #listeningQuizPage .quiz-validation-message strong {
            display: block;

            margin-bottom: 2px;

            color: inherit !important;

            font-size: 14px;
            font-weight: 800;
        }

        #listeningQuizPage .quiz-validation-message p {
            margin: 0;

            color: inherit !important;

            font-size: 13px;
            line-height: 1.5;
        }

        /*
        |--------------------------------------------------------------------------
        | TOMBOL SUBMIT
        |--------------------------------------------------------------------------
        */

        #listeningQuizSubmitArea {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;

            width: 100% !important;
            margin: 0 !important;
            padding: 20px 0 2px !important;

            border: 0 !important;

            background: transparent !important;
            background-color: transparent !important;

            box-shadow: none !important;
        }

        #listeningQuizSubmitButton {
            position: relative !important;

            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 10px !important;

            width: auto !important;
            min-width: 280px !important;
            min-height: 54px !important;

            margin: 0 !important;
            padding: 14px 26px !important;

            border: 1px solid #047857 !important;
            border-radius: 15px !important;

            background: #059669 !important;
            background-color: #059669 !important;
            background-image:
                linear-gradient(
                    135deg,
                    #10b981 0%,
                    #059669 48%,
                    #047857 100%
                ) !important;

            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;

            opacity: 1 !important;
            visibility: visible !important;

            font-family: inherit !important;
            font-size: 15px !important;
            font-weight: 800 !important;
            line-height: 1.2 !important;

            text-decoration: none !important;
            text-shadow: none !important;

            box-shadow:
                0 12px 24px rgba(5, 150, 105, 0.26),
                0 3px 8px rgba(4, 120, 87, 0.16) !important;

            mix-blend-mode: normal !important;
            filter: none !important;

            cursor: pointer !important;

            appearance: none !important;
            -webkit-appearance: none !important;

            isolation: isolate;

            transition:
                transform 160ms ease,
                box-shadow 160ms ease,
                filter 160ms ease !important;
        }

        #listeningQuizSubmitButton *,
        #listeningQuizSubmitButton span,
        #listeningQuizSubmitButton svg {
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;

            opacity: 1 !important;
            visibility: visible !important;

            mix-blend-mode: normal !important;
        }

        #listeningQuizSubmitButton svg {
            width: 20px !important;
            height: 20px !important;
            flex-shrink: 0;

            fill: none !important;
            stroke: #ffffff !important;
        }

        #listeningQuizSubmitButton:hover:not(:disabled) {
            background: #047857 !important;
            background-color: #047857 !important;
            background-image:
                linear-gradient(
                    135deg,
                    #059669 0%,
                    #047857 100%
                ) !important;

            transform: translateY(-2px);

            box-shadow:
                0 16px 30px rgba(5, 150, 105, 0.32),
                0 4px 10px rgba(4, 120, 87, 0.2) !important;
        }

        #listeningQuizSubmitButton:focus-visible {
            outline: 3px solid rgba(16, 185, 129, 0.35) !important;
            outline-offset: 3px !important;
        }

        #listeningQuizSubmitButton:disabled {
            background: #64748b !important;
            background-color: #64748b !important;
            background-image: none !important;

            border-color: #64748b !important;

            opacity: 0.72 !important;

            cursor: not-allowed !important;
            transform: none !important;

            box-shadow: none !important;
        }

        /*
        |--------------------------------------------------------------------------
        | HASIL
        |--------------------------------------------------------------------------
        */

        #listeningQuizPage .result-section {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        #listeningQuizPage .result-card {
            padding: 42px 24px;
            text-align: center;
        }

        #listeningQuizPage .result-icon {
            margin-bottom: 14px;

            font-size: 54px;
            line-height: 1;
        }

        #listeningQuizPage .result-title {
            margin: 0;

            color: var(--page-text) !important;

            font-size: clamp(28px, 4vw, 40px);
            font-weight: 900;
            line-height: 1.2;
        }

        #listeningQuizPage .result-description {
            margin: 9px 0 0;

            color: var(--muted-text) !important;

            font-size: 15px;
        }

        #listeningQuizPage .result-score-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;

            max-width: 340px;
            margin: 28px auto 0;
            padding: 23px;

            border: 1px solid var(--purple-border);
            border-radius: 22px;

            background-color: var(--purple-background) !important;
        }

        #listeningQuizPage .result-score-label {
            color: var(--muted-text) !important;

            font-size: 14px;
            font-weight: 700;
        }

        #listeningQuizPage .result-score-value {
            color: var(--purple-text) !important;

            font-size: clamp(52px, 8vw, 72px);
            font-weight: 900;
            line-height: 1;
        }

        #listeningQuizPage .result-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        #listeningQuizPage .result-secondary-button,
        #listeningQuizPage .result-primary-button {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;

            min-height: 50px;
            padding: 13px 20px;

            border-radius: 14px;

            font-size: 14px;
            font-weight: 800;

            text-align: center;
            text-decoration: none !important;

            transition:
                transform 160ms ease,
                background-color 160ms ease;
        }

        #listeningQuizPage .result-secondary-button {
            border: 1px solid var(--card-border) !important;

            background-color: var(--card-background) !important;
            color: var(--page-text) !important;
        }

        #listeningQuizPage .result-secondary-button:hover {
            background-color: var(--soft-background) !important;

            transform: translateY(-1px);
        }

        #listeningQuizPage .result-primary-button {
            border: 1px solid #6d28d9 !important;

            background:
                linear-gradient(
                    135deg,
                    #9333ea,
                    #4f46e5
                ) !important;

            color: #ffffff !important;

            box-shadow:
                0 9px 20px rgba(126, 34, 206, 0.2);
        }

        #listeningQuizPage .result-primary-button:hover {
            transform: translateY(-1px);

            filter: brightness(1.04);
        }

        /*
        |--------------------------------------------------------------------------
        | EMPTY STATE
        |--------------------------------------------------------------------------
        */

        #listeningQuizPage .empty-state {
            padding: 52px 24px;
            text-align: center;
        }

        #listeningQuizPage .empty-state-icon {
            margin-bottom: 14px;

            font-size: 50px;
        }

        #listeningQuizPage .empty-state-title {
            margin: 0;

            color: var(--page-text) !important;

            font-size: 25px;
            font-weight: 900;
        }

        #listeningQuizPage .empty-state-description {
            margin: 8px 0 0;

            color: var(--muted-text) !important;

            font-size: 14px;
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 767px) {
            #listeningQuizPage {
                gap: 18px;
            }

            #listeningQuizPage .listening-card {
                border-radius: 22px;
            }

            #listeningQuizPage .quiz-header-card {
                padding: 22px;
            }

            #listeningQuizPage .quiz-header-content {
                align-items: stretch;
                flex-direction: column;
                gap: 18px;
            }

            #listeningQuizPage .quiz-total-card {
                width: 100%;
                min-width: 0;
            }

            #listeningQuizPage .main-audio-card {
                padding: 16px;
            }

            #listeningQuizPage .main-audio-row,
            #listeningQuizPage .question-audio-box {
                align-items: stretch;
                flex-direction: column;
            }

            #listeningQuizPage .question-card {
                padding: 19px;
            }

            #listeningQuizPage .question-header {
                margin-bottom: 15px;
            }

            #listeningQuizPage .answer-option {
                min-height: 50px;
                padding: 12px 14px;
            }

            #listeningQuizPage .answer-text {
                font-size: 14px;
            }

            #listeningQuizSubmitArea {
                padding-top: 16px !important;
            }

            #listeningQuizSubmitButton {
                width: 100% !important;
                min-width: 0 !important;
            }

            #listeningQuizPage .result-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const completeUrl = @json(
                route('student.listening.complete', $lesson)
            );

            const csrfToken = @json(csrf_token());

            const quizCard = document.getElementById('quizCard');
            const resultSection = document.getElementById('resultSection');
            const finalScore = document.getElementById('finalScore');

            const submitBtn = document.getElementById(
                'listeningQuizSubmitButton'
            );

            const submitBtnText = document.getElementById(
                'submitBtnText'
            );

            const submitButtonIcon = document.getElementById(
                'submitButtonIcon'
            );

            const validationMessage = document.getElementById(
                'quizValidationMessage'
            );

            const validationText = document.getElementById(
                'quizValidationText'
            );

            const questionItems = document.querySelectorAll(
                '#listeningQuizPage .question-item'
            );

            const radioInputs = document.querySelectorAll(
                '#listeningQuizPage .answer-list input[type="radio"]'
            );

            /*
             * Fallback tambahan agar style global tidak dapat
             * membuat tombol transparan.
             */
            function forceSubmitButtonStyle() {
                if (!submitBtn || submitBtn.disabled) {
                    return;
                }

                submitBtn.style.setProperty(
                    'background-color',
                    '#059669',
                    'important'
                );

                submitBtn.style.setProperty(
                    'background-image',
                    'linear-gradient(135deg, #10b981 0%, #059669 48%, #047857 100%)',
                    'important'
                );

                submitBtn.style.setProperty(
                    'border-color',
                    '#047857',
                    'important'
                );

                submitBtn.style.setProperty(
                    'color',
                    '#ffffff',
                    'important'
                );

                submitBtn.style.setProperty(
                    '-webkit-text-fill-color',
                    '#ffffff',
                    'important'
                );

                submitBtn.style.setProperty(
                    'opacity',
                    '1',
                    'important'
                );

                submitBtn.style.setProperty(
                    'visibility',
                    'visible',
                    'important'
                );
            }

            forceSubmitButtonStyle();

            /*
             * Memastikan style tetap benar ketika theme diganti.
             */
            const themeObserver = new MutationObserver(function () {
                forceSubmitButtonStyle();
            });

            themeObserver.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class']
            });

            /*
             * Mengatur tampilan jawaban yang dipilih.
             */
            radioInputs.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    const answerList = radio.closest('.answer-list');
                    const questionCard = radio.closest('.question-item');

                    if (answerList) {
                        answerList
                            .querySelectorAll('.answer-option')
                            .forEach(function (optionLabel) {
                                optionLabel.classList.remove(
                                    'is-selected'
                                );
                            });
                    }

                    const selectedLabel = radio.closest('.answer-option');

                    if (selectedLabel) {
                        selectedLabel.classList.add('is-selected');
                    }

                    if (questionCard) {
                        questionCard.classList.remove('is-unanswered');
                    }

                    if (validationMessage) {
                        validationMessage.classList.add('is-hidden');
                    }
                });
            });

            if (!submitBtn) {
                return;
            }

            submitBtn.addEventListener('click', async function () {
                const answers = {};

                let unansweredCount = 0;
                let firstUnansweredQuestion = null;

                questionItems.forEach(function (item) {
                    const questionId = item.dataset.questionId;

                    const selectedAnswer = item.querySelector(
                        'input[name="question_' +
                        questionId +
                        '"]:checked'
                    );

                    item.classList.remove('is-unanswered');

                    if (!selectedAnswer) {
                        unansweredCount += 1;
                        item.classList.add('is-unanswered');

                        if (!firstUnansweredQuestion) {
                            firstUnansweredQuestion = item;
                        }

                        return;
                    }

                    answers[questionId] = selectedAnswer.value;
                });

                if (unansweredCount > 0) {
                    if (validationText) {
                        validationText.textContent =
                            'Masih ada ' +
                            unansweredCount +
                            ' soal yang belum dijawab.';
                    }

                    if (validationMessage) {
                        validationMessage.classList.remove('is-hidden');
                    }

                    if (firstUnansweredQuestion) {
                        firstUnansweredQuestion.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }

                    return;
                }

                if (validationMessage) {
                    validationMessage.classList.add('is-hidden');
                }

                submitBtn.disabled = true;

                if (submitBtnText) {
                    submitBtnText.textContent = 'Menyimpan Jawaban...';
                }

                if (submitButtonIcon) {
                    submitButtonIcon.style.display = 'none';
                }

                try {
                    const response = await fetch(completeUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            answers: answers
                        })
                    });

                    const result = await response
                        .json()
                        .catch(function () {
                            return {};
                        });

                    if (!response.ok || result.success !== true) {
                        let errorMessage =
                            result.message ||
                            'Jawaban gagal disimpan. Silakan coba lagi.';

                        if (result.errors) {
                            const firstError = Object.values(
                                result.errors
                            )[0];

                            if (
                                Array.isArray(firstError) &&
                                firstError.length > 0
                            ) {
                                errorMessage = firstError[0];
                            }
                        }

                        throw new Error(errorMessage);
                    }

                    if (finalScore) {
                        finalScore.textContent = result.score ?? 0;
                    }

                    document
                        .querySelectorAll(
                            '#listeningQuizPage audio'
                        )
                        .forEach(function (audio) {
                            audio.pause();
                        });

                    if (quizCard) {
                        quizCard.classList.add('is-hidden');
                    }

                    if (resultSection) {
                        resultSection.classList.remove('is-hidden');

                        resultSection.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                } catch (error) {
                    console.error(
                        'Listening quiz submission error:',
                        error
                    );

                    alert(
                        error.message ||
                        'Terjadi kesalahan saat menyimpan jawaban.'
                    );

                    submitBtn.disabled = false;

                    if (submitBtnText) {
                        submitBtnText.textContent =
                            'Submit Semua Jawaban';
                    }

                    if (submitButtonIcon) {
                        submitButtonIcon.style.display = '';
                    }

                    forceSubmitButtonStyle();
                }
            });
        });
    </script>

</x-app-layout>