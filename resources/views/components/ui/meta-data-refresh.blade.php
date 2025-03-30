@script
<script>
    $wire.on('updateMetaTags', (event) => {
        // Update title
        document.title = event.metaTags.title;

        // Update meta description
        const descriptionTag = document.querySelector('meta[name="description"]');
        if (descriptionTag instanceof HTMLMetaElement) {
            descriptionTag.content = event.metaTags.description;
        }

        // Update OpenGraph tags
        const ogTags = {
            title: document.querySelector('meta[property="og:title"]'),
            description: document.querySelector('meta[property="og:description"]'),
            image: document.querySelector('meta[property="og:image"]')
        };

        if (ogTags.title instanceof HTMLMetaElement) ogTags.title.content = event.metaTags.title;
        if (ogTags.description instanceof HTMLMetaElement) ogTags.description.content = event.metaTags.description;
        if (ogTags.image instanceof HTMLMetaElement) ogTags.image.content = event.metaTags.image;

        // Update Twitter tags
        const twitterTags = {
            title: document.querySelector('meta[name="twitter:title"]'),
            description: document.querySelector('meta[name="twitter:description"]'),
            image: document.querySelector('meta[name="twitter:image"]')
        };

        if (twitterTags.title instanceof HTMLMetaElement) twitterTags.title.content = event.metaTags.title;
        if (twitterTags.description instanceof HTMLMetaElement) twitterTags.description.content = event.metaTags.description;
        if (twitterTags.image instanceof HTMLMetaElement) twitterTags.image.content = event.metaTags.image;
    });
</script>
@endscript
