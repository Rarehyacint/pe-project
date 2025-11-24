import { EditorState } from "@codemirror/state";
import { EditorView, keymap } from "@codemirror/view";
import { basicSetup } from "codemirror";
import {python} from "@codemirror/lang-python"
import { java } from "@codemirror/lang-java";
import {cpp} from "@codemirror/lang-cpp"
import {indentWithTab} from "@codemirror/commands"

    // Initialize Constant Variables
    const supported_languages = {};

    let previous_language = null;
    let currentLanguage = null;

    // Add ability to switch programming languages and have their own codes
    function switchLanguageFromEvent(event) {
        const selected_language = event.target.value;
        switchLanguage(selected_language);
    }

    function switchLanguage(language) {
        currentLanguage = language;
        if (!initial_solution_editor || !test_case_editor) return;

        let extension;
        switch (language) {
            case 'java':
                extension = java();
                break;
            case 'python':
                extension = python();
                break;
            case 'c++':
                extension = cpp();
                break;
            default:
                console.warn("Unsupported language:", language);
                return;
        }
    }

    function loadSupportedLanguages(question_supported_languages) {
        const currentLanguage =  Object.keys(question_supported_languages)[0];
        for (const language in question_supported_languages) {
            const values = question_supported_languages[language];
            let extension;

            switch (language) {
                case "python":
                    extension = python();
                    break;
                case "java":
                    extension = java();
                    break;
                case "c++":
                    extension = cpp();
                    break;
                default:
                    console.warn(`Unsupported language: ${language}`);
                    continue;
            }

            const initial_solution_state = EditorState.create({
                doc: values.initial_solution || "",
                extensions: [
                    basicSetup, 
                    keymap.of([indentWithTab]), 
                    EditorView.domEventHandlers({
                        paste: (event, view) => {
                            event.preventDefault();
                            return true;
                        },
                        copy: (event, view) => {
                            event.preventDefault(); 
                            return true;
                        },
                        cut: (event, view) => {
                            event.preventDefault(); 
                            return true;
                        },
                    }),
                    extension]
            });
            const test_case_state = EditorState.create({
                doc: values.test_case || "",
                extensions: [basicSetup, extension]
            });

            supported_languages[language] = {
                initial_solution: initial_solution_state,
                test_case: test_case_state,
                is_valid: true
            };

            if (currentLanguage === language) {
                initial_solution_editor.setState(initial_solution_state);
                test_case_editor.setState(test_case_state);
            }
        }
    }

    let initial_solution_editor = null;
    let test_case_editor = null;

    function createEditor(parent, doc = '', languageExtension = null) {
        const extensions = [basicSetup];
        if (languageExtension) extensions.push(languageExtension);

        const state = EditorState.create({ doc, extensions });
        return new EditorView({ state, parent });
    }

    function initializeEditors() {
        const initial_solution_div = document.getElementById("initial-solution-div");
        const test_case_div = document.getElementById("test-case-div");
        const select_form = document.getElementById("answer[programming_language]");
        if (!initial_solution_div || !test_case_div || !select_form) {
            console.warn("Editor elements not found. Skipping initialization.");
            return;
        }

        // Create fresh editors every time after DOM swap
        initial_solution_editor = createEditor(initial_solution_div);
        test_case_editor = createEditor(test_case_div);

        initializeCodingQuestionPage();
    }


    function initializeCodingQuestionPage() {
        const select_form = document.getElementById("answer[programming_language]");
        if (!select_form) return;

        select_form.addEventListener('focus', () => {
        previous_language = select_form.value;
        });

        const select = document.getElementById('answer[programming_language]');
        if (!select || select.dataset.initialized === "true") return;
        loadSupportedLanguages(window.question_supported_languages);

        switchLanguageFromEvent({ target: { value: select.value } });
        select.dataset.initialized = "true"; 
    }

    function syncCodeMirrorToTextarea() {
        const textarea = document.getElementById('answer-input');
        if (textarea && initial_solution_editor) {
            setTimeout(() => {
                textarea.value = initial_solution_editor.state.doc.toString();
            }, 0); // flush any in-flight input
        }
    }

    function testStudentCode(){
        const select_form = document.getElementById('answer[programming_language]');
        document.getElementById('student-code-test-input').value = initial_solution_editor.state.doc.toString();
        document.getElementById('language-to-validate-input').value = select_form.value;
        return true;
    }

    document.body.addEventListener("htmx:configRequest", function (e) {
        const container = e.detail.target.querySelector("#coding-question");
        if (!container) return;
        try {
            const editor = initial_solution_editor;
            const textarea = document.getElementById("answer-input");

            if (editor && textarea) {
                textarea.value = editor.state.doc.toString();
                e.detail.parameters["answer[code]"] = textarea.value;

            }

        } catch (e) {
            console.error("htmx:configRequest failed", e);
        }
    });

    document.body.addEventListener("htmx:afterSwap", function (e) {
        const container = e.detail.elt.querySelector("#coding-question");
        if (!container) return;

        try {

            const instruction = JSON.parse(container.dataset.instruction || '{}');
            const languageCodes = JSON.parse(container.dataset.languageCodes || '{}');
            window.instruction = instruction;
            window.question_supported_languages = languageCodes;

            // Actually reinitialize editors and load languages
            initializeEditors();
        } catch (e) {
            console.error("[HTMX] Failed to parse editor data:", e);
        }
    });
    document.addEventListener('htmx:beforeRequest', function () {
        const btn = document.getElementById('test-code-button');
        if (btn) btn.disabled = true;
    });

    document.body.addEventListener('htmx:configRequest', function(evt) {
        document.querySelectorAll('.question-button').forEach(b => {
            b.disabled = true;
            b.classList.add('opacity-50', 'pointer-events-none');
        });
    });

    document.addEventListener('htmx:afterRequest', function (evt) {
        const btn = document.getElementById('test-code-button');
        if (btn) btn.disabled = false;

        const clickedBtn = evt.detail.elt;

        // Enable and reset all buttons first
        document.querySelectorAll('.question-button').forEach(b => {
            b.disabled = false;
            b.classList.remove('text-blue-900', 'font-bold', 'pointer-events-none', 'opacity-50');
        });

        // Then disable and style the clicked button only
        if (clickedBtn && clickedBtn.classList.contains('question-button')) {
            clickedBtn.classList.add('text-blue-900', 'font-bold', 'pointer-events-none');
            clickedBtn.disabled = true;
        }
    });


window.initializeCodingQuestionPage = initializeCodingQuestionPage;
window.initializeEditors = initializeEditors; 
window.syncCodeMirrorToTextarea = syncCodeMirrorToTextarea;
window.testStudentCode = testStudentCode;

