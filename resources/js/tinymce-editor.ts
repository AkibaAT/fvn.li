declare global {
    interface Window {
        tinymce: any;
        initTinyMCE: () => void;
        destroyTinyMCE: () => void;
        updatePreview: (content: string) => void;
        openImagePickerDialog: () => void;
        Livewire: any;
    }
}

// Load TinyMCE from self-hosted files
const loadTinyMCE = () => {
    if (!document.querySelector('script[src*="tinymce.min.js"]')) {
        const script = document.createElement('script');
        script.src = '/assets/tinymce/tinymce.min.js';
        script.onerror = () => {
            console.error('Failed to load TinyMCE script');
        };
        document.head.appendChild(script);
    }
};

// Load TinyMCE on module import
loadTinyMCE();

// Function to detect dark mode
const isDarkMode = () => {
    return document.documentElement.classList.contains('dark') || 
           window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const editorConfig = {
    selector: '#description',
    height: 600,
    min_height: 400,
    max_height: 800,
    menubar: false,
    resize: 'both',
    autoresize_bottom_margin: 50,
    autoresize_min_height: 600,
    autoresize_max_height: 1200,

    // URL handling - use root-relative URLs (no domain, but absolute from root)
    relative_urls: false,
    remove_script_host: true,
    convert_urls: true,
    base_url: '/assets/tinymce',
    suffix: '.min',
    license_key: 'gpl',
    skin: isDarkMode() ? 'oxide-dark' : 'oxide',
    content_css: isDarkMode() ? 'dark' : 'default',
    onbeforeunload: false,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'searchreplace', 'visualblocks', 'fullscreen', 'insertdatetime', 'media',
        'table', 'help', 'wordcount', 'code', 'codesample', 'autoresize'
    ],
    toolbar: 'undo redo | blocks | ' +
        'bold italic backcolor | alignleft aligncenter ' +
        'alignright alignjustify | bullist numlist outdent indent | ' +
        'removeformat | image imagepicker media link | code | fullscreen | help',
    content_style: `
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            font-size: 16px;
            line-height: 1.8;
            margin: 0.75rem;
            background-color: ${isDarkMode() ? '#1f2937' : '#ffffff'};
            color: ${isDarkMode() ? '#f9fafb' : '#111827'};
        }
        p {
            margin-bottom: 1.2em;
            line-height: 1.8;
        }
        h1, h2, h3, h4, h5, h6 {
            line-height: 1.4;
            margin-top: 1.5em;
            margin-bottom: 0.8em;
        }
        ul, ol {
            line-height: 1.8;
            margin-bottom: 1.2em;
        }
        li {
            margin-bottom: 0.4em;
        }
        .game_description {
            /* Apply your custom game description styles here */
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
            border: 1px solid ${isDarkMode() ? '#4b5563' : '#ddd'};
            padding: 8px;
            background-color: ${isDarkMode() ? '#374151' : '#ffffff'};
        }
        a {
            color: ${isDarkMode() ? '#60a5fa' : '#2563eb'};
        }
        code {
            background-color: ${isDarkMode() ? '#374151' : '#f3f4f6'};
            color: ${isDarkMode() ? '#f9fafb' : '#1f2937'};
            padding: 2px 4px;
            border-radius: 3px;
        }
        pre {
            background-color: ${isDarkMode() ? '#111827' : '#f9fafb'};
            color: ${isDarkMode() ? '#f9fafb' : '#111827'};
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid ${isDarkMode() ? '#4b5563' : '#e5e7eb'};
        }
        blockquote {
            border-left: 4px solid ${isDarkMode() ? '#60a5fa' : '#2563eb'};
            padding-left: 1rem;
            margin-left: 0;
            font-style: italic;
            color: ${isDarkMode() ? '#d1d5db' : '#6b7280'};
        }
    `,
    setup: function (editor: any) {
        let updateTimeout: any;
        
        const updatePreviewContent = () => {
            const content = editor.getContent();
            if (typeof window.updatePreview === 'function') {
                (window as any).updatePreview(content);
            }
            
            // Debounced Livewire update only for data persistence
            clearTimeout(updateTimeout);
            updateTimeout = setTimeout(() => {
                try {
                    const editorElement = editor.getElement();
                    if (!editorElement) return;

                    const livewireComponent = editorElement.closest('[wire\\:id]');
                    if (livewireComponent && window.Livewire) {
                        const componentId = livewireComponent.getAttribute('wire:id');
                        if (componentId) {
                            const component = window.Livewire.find(componentId);
                            if (component) {
                                component.set('description', content, false); // false = don't re-render
                            }
                        }
                    }
                } catch (error) {
                    // Ignore timing-related update errors
                }
            }, 1000); // 1 second debounce for data sync
        };
        
        // Listen to content changes (typing, pasting)
        editor.on('input keyup paste', updatePreviewContent);
        
        // Listen to formatting changes (toolbar buttons, keyboard shortcuts)
        editor.on('ExecCommand', updatePreviewContent);
        
        // Listen to any content changes (catch-all for other operations)
        editor.on('change', updatePreviewContent);
        
        // Listen to undo/redo operations
        editor.on('undo redo', updatePreviewContent);

        editor.on('blur', function () {
            try {
                // Sync content on blur
                const content = editor.getContent();
                const editorElement = editor.getElement();
                if (!editorElement) return;

                const livewireComponent = editorElement.closest('[wire\\:id]');
                if (livewireComponent && window.Livewire) {
                    const componentId = livewireComponent.getAttribute('wire:id');
                    if (componentId) {
                        const component = window.Livewire.find(componentId);
                        if (component) {
                            component.set('description', content, false);
                        }
                    }
                }
            } catch (error) {
                // Ignore timing-related blur sync errors
            }
        });

        // Add custom image picker button
        editor.ui.registry.addButton('imagepicker', {
            text: 'Gallery',
            icon: 'gallery',
            tooltip: 'Insert image from gallery',
            onAction: function () {
                // Check if game ID is available
                const gameIdElement = document.querySelector('[data-game-id]') as HTMLElement;
                const gameId = gameIdElement?.dataset.gameId;

                if (!gameId) {
                    editor.notificationManager.open({
                        text: 'Game ID not found. Cannot load image gallery.',
                        type: 'error'
                    });
                    return;
                }

                // Set up event listener for image selection
                const handleImageSelection = (event: CustomEvent) => {
                    const imageUrl = event.detail.imageUrl;
                    editor.insertContent(`<img src="${imageUrl}" alt="Image" style="max-width: 100%; height: auto;" />`);
                    // Remove the event listener after use
                    window.removeEventListener('image-selected', handleImageSelection);
                };

                window.addEventListener('image-selected', handleImageSelection);

                // Open the dialog using the global function
                if ((window as any).openImagePickerDialog) {
                    (window as any).openImagePickerDialog();
                } else {
                    alert('Image gallery is not available. Please try refreshing the page.');
                }
            }
        });
    },
    images_upload_handler: function (blobInfo: any, progress: any) {
        return new Promise((resolve, reject) => {
            // Get game ID from the page
            const gameIdElement = document.querySelector('[data-game-id]') as HTMLElement;
            const gameId = gameIdElement?.dataset.gameId;

            if (!gameId) {
                reject('Game ID not found');
                return;
            }

            const formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            formData.append('game_id', gameId);

            fetch('/api/upload-editor-image', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(result => {
                if (result.location) {
                    resolve(result.location);
                } else {
                    reject(result.error || 'Upload failed - no location returned');
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                reject(error.message || 'Upload failed');
            });
        });
    },
    // Configure image dialog
    image_advtab: true,
    image_uploadtab: true,
    // Configure link dialog
    link_default_target: '_blank',
    link_assume_external_targets: true,
    // Configure table (table_responsive_width removed in TinyMCE 7+)
    table_use_colgroups: true,
    // Configure code highlighting
    codesample_languages: [
        { text: 'HTML/XML', value: 'markup' },
        { text: 'JavaScript', value: 'javascript' },
        { text: 'CSS', value: 'css' },
        { text: 'Python', value: 'python' },
        { text: 'Java', value: 'java' },
        { text: 'C#', value: 'csharp' },
        { text: 'PHP', value: 'php' },
        { text: 'Ruby', value: 'ruby' },
        { text: 'C++', value: 'cpp' },
        { text: 'C', value: 'c' }
    ]
};

// Initialize TinyMCE when needed
window.initTinyMCE = function() {
    const initEditor = () => {
        // Destroy existing instance first
        if (window.tinymce?.get('description')) {
            window.tinymce.get('description').destroy();
        }

        const element = document.getElementById('description');
        if (element && window.tinymce) {
            // Update configuration based on current dark mode state
            const currentConfig = {
                ...editorConfig,
                skin: isDarkMode() ? 'oxide-dark' : 'oxide',
                content_css: isDarkMode() ? 'dark' : 'default',
                content_style: `
                    body { 
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif; 
                        font-size: 14px;
                        line-height: 1.6;
                        margin: 1rem;
                        background-color: ${isDarkMode() ? '#1f2937' : '#ffffff'};
                        color: ${isDarkMode() ? '#f9fafb' : '#111827'};
                    }
                    .game_description {
                        /* Apply your custom game description styles here */
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
                        border: 1px solid ${isDarkMode() ? '#4b5563' : '#ddd'};
                        padding: 8px;
                        background-color: ${isDarkMode() ? '#374151' : '#ffffff'};
                    }
                    a {
                        color: ${isDarkMode() ? '#60a5fa' : '#2563eb'};
                    }
                    code {
                        background-color: ${isDarkMode() ? '#374151' : '#f3f4f6'};
                        color: ${isDarkMode() ? '#f9fafb' : '#1f2937'};
                        padding: 2px 4px;
                        border-radius: 3px;
                    }
                    pre {
                        background-color: ${isDarkMode() ? '#111827' : '#f9fafb'};
                        color: ${isDarkMode() ? '#f9fafb' : '#111827'};
                        padding: 1rem;
                        border-radius: 6px;
                        border: 1px solid ${isDarkMode() ? '#4b5563' : '#e5e7eb'};
                    }
                    blockquote {
                        border-left: 4px solid ${isDarkMode() ? '#60a5fa' : '#2563eb'};
                        padding-left: 1rem;
                        margin-left: 0;
                        font-style: italic;
                        color: ${isDarkMode() ? '#d1d5db' : '#6b7280'};
                    }
                `
            };
            
            window.tinymce.init(currentConfig).then(() => {
                console.log('TinyMCE initialized successfully');
            }).catch((error: any) => {
                console.error('TinyMCE initialization failed:', error);
            });
        }
    };

    if (window.tinymce) {
        console.log('TinyMCE already available, initializing');
        initEditor();
    } else {
        console.log('TinyMCE not available, waiting...');
        // Wait for TinyMCE to load
        let attempts = 0;
        const checkTinyMCE = () => {
            attempts++;
            console.log(`Checking for TinyMCE, attempt ${attempts}`);
            if (window.tinymce) {
                console.log('TinyMCE now available, initializing');
                initEditor();
            } else if (attempts < 50) { // Wait up to 5 seconds
                setTimeout(checkTinyMCE, 100);
            } else {
                console.error('TinyMCE failed to load after 5 seconds');
                // Try to load TinyMCE again
                loadTinyMCE();
            }
        };
        checkTinyMCE();
    }
};

// Destroy TinyMCE when needed
window.destroyTinyMCE = function() {
    if (window.tinymce?.get('description')) {
        window.tinymce.get('description').destroy();
    }
};

// Initialize on page load if element exists
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('description')) {
        window.initTinyMCE();
    }
});

// Listen for Livewire navigation events
document.addEventListener('livewire:navigated', () => {
    if (document.getElementById('description')) {
        window.initTinyMCE();
    }
});

// Listen for when editing mode changes
document.addEventListener('livewire:updated', () => {
    const descriptionElement = document.getElementById('description');
    if (descriptionElement && !window.tinymce?.get('description')) {
        window.initTinyMCE();
    }
});