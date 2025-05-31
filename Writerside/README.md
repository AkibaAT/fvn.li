# FVN.li Documentation

This directory contains the Writerside documentation project for FVN.li.

## Documentation Website

The documentation is automatically built and deployed to GitHub Pages at:
**https://akibaat.github.io/fvn.li/**

## Local Development

### Prerequisites

- [JetBrains Writerside](https://www.jetbrains.com/writerside/) IDE or
- [Docker](https://www.docker.com/) for building without the IDE

### Using Writerside IDE

1. Open the `Writerside` directory in JetBrains Writerside
2. The project will automatically load with the correct configuration
3. Use the built-in preview to see changes in real-time
4. Build locally using the IDE's build tools

### Using Docker

Build the documentation using Docker:

```bash
# From the project root
docker run --rm -v $(pwd)/Writerside:/opt/sources \
  jetbrains/writerside:241.18775 \
  /opt/builder/bin/idea.sh helpbuilderinspect \
  --source-dir /opt/sources \
  --product in \
  --runner other \
  --output-dir /opt/sources/artifacts/
```

## Project Structure

```
Writerside/
├── cfg/
│   └── buildprofiles.xml      # Build configuration
├── images/                    # Documentation images
├── topics/                    # Documentation content
│   ├── README.md             # Main landing page
│   └── docs/
│       ├── fix-commands.md   # Fix commands overview
│       └── commands/         # Individual command docs
├── in.tree                   # Navigation structure
├── writerside.cfg           # Project configuration
└── README.md                # This file
```

## Automatic Deployment

The documentation is automatically built and deployed via GitHub Actions:

- **Trigger**: Pushes to `development` or `main` branches that modify files in `Writerside/`
- **Build**: Uses JetBrains Writerside Docker image
- **Quality Gate**: Runs documentation tests and validation
- **Deploy**: Publishes to GitHub Pages

### Manual Deployment

You can manually trigger the deployment workflow:

1. Go to the repository's Actions tab
2. Select "Deploy Writerside Documentation"
3. Click "Run workflow"

## Contributing to Documentation

### Adding New Pages

1. Create a new `.md` file in the appropriate `topics/` subdirectory
2. Add the page to the navigation in `in.tree`
3. Use Writerside markup for enhanced formatting

### Writerside Features

The documentation uses advanced Writerside features:

- **Tables** with proper formatting
- **Tabs** for multiple examples
- **Code blocks** with syntax highlighting
- **Procedures** for step-by-step instructions
- **Definition lists** for options and concepts
- **Cross-references** between pages

### Example Markup

```markdown
# Page Title

## Section with Tabs

<tabs>
<tab title="Example 1">
<code-block lang="bash">
php artisan command --option
</code-block>
</tab>
<tab title="Example 2">
<code-block lang="bash">
php artisan command --other-option
</code-block>
</tab>
</tabs>

## Procedure Example

<procedure title="How to do something">
<step>First step description</step>
<step>Second step description</step>
<step>Final step description</step>
</procedure>

## Definition List

<deflist>
<def title="Term 1">
Definition of the first term
</def>
<def title="Term 2">
Definition of the second term
</def>
</deflist>
```

## Configuration

### Build Profiles

The `cfg/buildprofiles.xml` file contains:

- **Web root**: GitHub Pages URL
- **Primary color**: Blue theme
- **Browser edits**: Disabled for public docs
- **Search indexing**: Enabled

### Navigation

The `in.tree` file defines the documentation structure and navigation menu.

## Troubleshooting

### Build Failures

If the GitHub Action fails:

1. Check the Actions tab for error details
2. Verify all `.md` files have valid Writerside markup
3. Ensure all cross-references point to existing files
4. Check that `in.tree` references all topic files correctly

### Local Preview Issues

If local preview doesn't work:

1. Ensure you're using a compatible Writerside version
2. Check that all file paths in `in.tree` are correct
3. Verify image references point to files in the `images/` directory

## Resources

- [Writerside Documentation](https://www.jetbrains.com/help/writerside/)
- [Writerside Markup Reference](https://www.jetbrains.com/help/writerside/markup-reference.html)
- [GitHub Actions for Writerside](https://github.com/JetBrains/writerside-github-action)
