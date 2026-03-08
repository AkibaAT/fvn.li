// TinyMCE React wrapper
import {Editor} from '@tinymce/tinymce-react';
import {useEffect, useMemo, useState} from 'react';
import {useDarkMode} from '@/hooks/useSSR';
// IMPORTANT: We intentionally do NOT import 'tinymce/*' modules here so the editor
// loads from self-hosted assets at runtime instead of being bundled into the page.
import type {Editor as TinyMCE} from 'tinymce';

type TinyBlobInfo = { blob: () => Blob; filename: () => string };

interface TinyMCEEditorProps {
    content: string;
    onUpdate: (content: string) => void;
    editable?: boolean;
    placeholder?: string;
    height?: number;
    gameId?: number | string; // used for uploads and gallery
    disableImages?: boolean; // hide image-related plugins and toolbar buttons
}

// Function to detect dark mode (SSR-safe)
const isDarkMode = (document: Document | null, isDark: boolean) => {
    return document?.documentElement.classList.contains('dark') || isDark;
};

export default function TinyMCEEditor({
                                          content,
                                          onUpdate,
                                          editable = true,
                                          placeholder = 'Start writing...',
                                          height = 400,
                                          gameId,
                                          disableImages = false,
                                      }: TinyMCEEditorProps) {
    const isDark = useDarkMode();
    const [isClient, setIsClient] = useState(false);

    useEffect(() => {
        setIsClient(true);
    }, []);

    const handleEditorChange = (content: string) => {
        onUpdate(content);
    };

    // Memoize init config so it updates when theme changes but not on every render
    const initConfig = useMemo(() => ({
        // Don't load TinyMCE on server side
        ...(isClient && {
        // Use self-hosted TinyMCE like production
        base_url: '/assets/tinymce',
        suffix: '.min',

        height,
        min_height: 400,
        max_height: 800,
        menubar: false,
        resize: 'both' as const,
        // autoresize behavior
        autoresize_bottom_margin: 50,
        autoresize_min_height: Math.max(height, 400),
        autoresize_max_height: 1200,

        // URL handling - use root-relative URLs
        relative_urls: false,
        remove_script_host: true,
        convert_urls: true,

        skin: isDarkMode(document, isDark) ? 'oxide-dark' : 'oxide',
        content_css: isDarkMode(document, isDark) ? 'dark' : 'default',

        plugins: [
            'advlist', 'autolink', 'lists', 'link',
            ...(disableImages ? [] : ['image']),
            'charmap', 'preview',
            'searchreplace', 'visualblocks', 'fullscreen', 'insertdatetime',
            ...(disableImages ? [] : ['media']),
            'table', 'help', 'wordcount', 'code', 'codesample', 'autoresize'
        ],
        toolbar: disableImages
            ? 'undo redo | blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link | code | fullscreen | help'
            : 'undo redo | blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | image imagepicker media link | code | fullscreen | help',
        content_style: `
          body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            font-size: 1rem;
            line-height: 1.75;
            margin: 0.75rem;
            background-color: var(--color-editor-background);
            color: var(--color-editor-text);
          }
          p {
            margin-top: 1.25em;
            margin-bottom: 1.25em;
            line-height: 1.75;
          }
          p:first-child {
            margin-top: 0;
          }
          h1, h2, h3, h4, h5, h6 {
            line-height: 1.4;
            margin-top: 1.5em;
            margin-bottom: 0.8em;
          }
          ul, ol {
            line-height: 1.8;
            margin-bottom: 1.2em;
            padding-left: 1.625em;
          }
          ul {
            list-style-type: disc;
          }
          ol {
            list-style-type: decimal;
          }
          ul ul {
            list-style-type: circle;
          }
          ul ul ul {
            list-style-type: square;
          }
          li {
            margin-bottom: 0.4em;
          }
          img {
            max-width: 100%;
            height: auto;
          }
          table {
            width: 100%;
            border-collapse: collapse;
          }
          table td, table th {
            border: 1px solid var(--color-editor-toolbar-border);
            padding: 8px;
            background-color: var(--color-editor-toolbar-background);
          }
          a {
            color: var(--color-editor-link);
          }
          code {
            background-color: var(--color-editor-content-background);
            color: var(--color-editor-content-text);
            padding: 2px 4px;
            border-radius: 3px;
          }
          pre {
            background-color: var(--color-editor-content-background);
            color: var(--color-editor-content-text);
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid var(--color-editor-content-border);
          }
          blockquote {
            border-left: 4px solid var(--color-editor-blockquote-border);
            padding-left: 1rem;
            margin-left: 0;
            font-style: italic;
            color: var(--color-editor-pre-text);
          }
        `,
        placeholder,
        branding: false,
        promotion: false,
        elementpath: false,
        statusbar: true,

        // Configure image dialog
        image_advtab: true,
        image_uploadtab: true,

        // Configure link dialog
        link_default_target: '_blank',
        link_assume_external_targets: true,

        // Configure table
        table_use_colgroups: true,

        // Configure code highlighting
        codesample_languages: [
            {text: 'HTML/XML', value: 'markup'},
            {text: 'JavaScript', value: 'javascript'},
            {text: 'CSS', value: 'css'},
            {text: 'Python', value: 'python'},
            {text: 'Java', value: 'java'},
            {text: 'C#', value: 'csharp'},
            {text: 'PHP', value: 'php'},
            {text: 'Ruby', value: 'ruby'},
            {text: 'C++', value: 'cpp'},
            {text: 'C', value: 'c'}
        ],

        // Content filtering - preserve existing HTML
        valid_elements: '*[*]',
        extended_valid_elements: '*[*]',
        verify_html: false,
        keep_styles: true,

        // Better paste handling
        paste_retain_style_properties: 'color font-size font-family background-color',
        paste_remove_styles_if_webkit: false,

        // Custom setup to add gallery button and change hooks
        setup: (editor: TinyMCE) => {
            // Custom image picker button mirroring production behavior
            editor.ui.registry.addButton('imagepicker', {
                text: 'Gallery',
                icon: 'gallery',
                tooltip: 'Insert image from gallery',
                onAction: function () {
                    // Prefer provided gameId prop; fallback to data attribute used on non-React pages
                    const gid = gameId ?? (document?.querySelector('[data-game-id]') as HTMLElement)?.dataset.gameId;
                    if (!gid) {
                        editor.notificationManager.open({
                            text: 'Game ID not found. Cannot load image gallery.',
                            type: 'error'
                        });
                        return;
                    }

                    const handleImageSelection = (event: Event) => {
                        const imageUrl = (event as CustomEvent<{ imageUrl: string }>).detail?.imageUrl;
                        if (imageUrl) {
                            editor.insertContent(`<img src="${imageUrl}" alt="Image" style="max-width: 100%; height: auto;" />`);
                        }
                        window.removeEventListener('image-selected', handleImageSelection as EventListener);
                    };
                    window.addEventListener('image-selected', handleImageSelection as EventListener);

                    const w = window as Window & { openImagePickerDialog?: () => void };
                    if (typeof w.openImagePickerDialog === 'function') {
                        w.openImagePickerDialog();
                    } else {
                        // No gallery available in React yet; inform user
                        editor.notificationManager.open({
                            text: 'Image gallery is not available on this page.',
                            type: 'warning'
                        });
                    }
                }
            });
        },

        // Drag & drop / paste upload handler
        images_upload_handler: (blobInfo: TinyBlobInfo) => {
            return new Promise<string>((resolve, reject) => {
                const gid = gameId ?? (document?.querySelector('[data-game-id]') as HTMLElement)?.dataset.gameId;
                if (!gid) {
                    reject('Game ID not found');
                    return;
                }

                const formData = new FormData();
                formData.append('file', blobInfo.blob(), blobInfo.filename());
                formData.append('game_id', String(gid));

                fetch(`/react-api/upload-editor-image?t=${Date.now()}`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document?.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    }
                })
                    .then((res) => res.ok ? res.json() : Promise.reject(new Error(`HTTP ${res.status}`)))
                    .then((result) => {
                        if (result?.location) {
                            resolve(result.location as string);
                        } else {
                            reject(result?.error || 'Upload failed - no location returned');
                        }
                    })
                    .catch((err) => {
                        console.error('Upload error:', err);
                        reject(err?.message || 'Upload failed');
                    });
            });
        },
        // Use self-hosted TinyMCE like production
        }),
    }), [height, placeholder, gameId, isClient, isDark, disableImages]);

    // Don't render TinyMCE on server side
    if (!isClient) {
        return (
            <div className="min-h-[400px] border border-gray-300 rounded-md bg-gray-50 dark:border-gray-600 dark:bg-gray-800 flex items-center justify-center">
                <div className="text-gray-500 dark:text-gray-400">Loading editor...</div>
            </div>
        );
    }

    return (
        <Editor
            value={content}
            onEditorChange={handleEditorChange}
            disabled={!editable}
            // Load TinyMCE core from self-hosted assets to avoid bundling
            tinymceScriptSrc="/assets/tinymce/tinymce.min.js"
            init={initConfig}
            licenseKey="gpl"
        />
    );
}
