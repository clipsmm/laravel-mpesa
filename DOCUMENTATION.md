# Documentation Deployment

This package uses **Docsify** for documentation and **GitHub Actions** for automatic deployment to GitHub Pages.

## Documentation System

- **Docsify** - A magical documentation site generator
- **GitHub Pages** - Free hosting for the docs
- **GitHub Actions** - Automatic deployment on push

## Local Development

### Preview Documentation Locally

```bash
# Option 1: Using PHP built-in server
composer docs:serve

# Option 2: Using Python
cd docs
python3 -m http.server 8000

# Option 3: Using Node.js (install docsify-cli)
npm i docsify-cli -g
docsify serve docs
```

Then open http://localhost:8000 in your browser.

## Deployment

### Automatic Deployment

Documentation automatically deploys to GitHub Pages when you push to `main`:

```bash
git add docs/
git commit -m "Update documentation"
git push origin main
```

The GitHub Actions workflow (`.github/workflows/docs.yml`) handles deployment.

### View Live Documentation

Once deployed, documentation is available at:

**https://clipsmm.github.io/laravel-mpesa/**

## Documentation Structure

```
docs/
├── index.html          # Docsify entry point
├── README.md           # Homepage
├── _sidebar.md         # Sidebar navigation
├── installation.md     # Installation guide
├── configuration.md    # Configuration guide
├── stk-push.md         # STK Push guide
├── stk-query.md        # STK Query guide
├── callbacks.md        # Callback handling
├── response-dtos.md    # Response DTOs guide
├── c2b-registration.md # C2B registration
├── logging.md          # Logging guide
├── testing.md          # Testing guide
├── security.md         # Security best practices
├── error-handling.md   # Error handling
├── troubleshooting.md  # Troubleshooting guide
├── development.md      # Contributing guide
├── api-reference.md    # Complete API reference
├── guides/             # Additional guides
│   ├── callback-controller-example.md
│   └── security-audit.md
└── .nojekyll           # Disables Jekyll processing
```

## Adding New Pages

1. Create a new markdown file in `docs/`:
   ```bash
   touch docs/new-feature.md
   ```

2. Add content using markdown:
   ```markdown
   # New Feature
   
   Description of the feature...
   
   ## Usage
   
   \`\`\`php
   // Code example
   \`\`\`
   ```

3. Add to sidebar navigation in `docs/_sidebar.md`:
   ```markdown
   - Usage
     - [New Feature](new-feature.md)
   ```

4. Commit and push:
   ```bash
   git add docs/
   git commit -m "Add new feature documentation"
   git push origin main
   ```

## Docsify Features

### Search

Full-text search is enabled via the search plugin.

### Copy Code

Code blocks have a "Copy" button for easy copying.

### Syntax Highlighting

Supports PHP, Bash, JSON, and more via Prism.js.

### Images

Images zoom on click via the zoom-image plugin.

### Pagination

Next/Previous navigation at the bottom of pages.

## GitHub Pages Setup

### Enable GitHub Pages

1. Go to repository Settings
2. Navigate to Pages
3. Set Source to "GitHub Actions"
4. Save

### First Deployment

The first deployment happens automatically when you push to `main` with changes in `docs/` directory.

## Workflow Details

The deployment workflow (`.github/workflows/docs.yml`):

1. **Triggers on:**
   - Push to `main` branch with changes in `docs/`
   - Manual workflow dispatch

2. **Process:**
   - Checks out the repository
   - Uploads `docs/` folder as artifact
   - Deploys to GitHub Pages

3. **Permissions:**
   - `contents: read` - Read repository
   - `pages: write` - Deploy to Pages
   - `id-token: write` - OIDC authentication

## Customization

### Theme

Change theme in `docs/index.html`:

```javascript
window.$docsify = {
  // ... other options
  theme: 'vue',  // or 'buble', 'dark', 'pure'
}
```

### Colors

Customize colors via CSS variables:

```css
:root {
  --theme-color: #00C853;
  --theme-color-dark: #00A344;
}
```

### Plugins

Add more plugins in `docs/index.html`:

```html
<!-- Example: Add emoji support -->
<script src="//cdn.jsdelivr.net/npm/docsify/lib/plugins/emoji.min.js"></script>
```

## Monitoring

### Check Deployment Status

1. Go to repository "Actions" tab
2. Click on latest "Deploy Documentation" workflow
3. View deployment status and logs

### Test After Deployment

```bash
# Check if site is live
curl -I https://clipsmm.github.io/laravel-mpesa/

# Check specific page
curl https://clipsmm.github.io/laravel-mpesa/installation
```

## Troubleshooting

### Documentation not updating?

1. **Check workflow status** in Actions tab
2. **Clear browser cache**
3. **Check file paths** are correct
4. **Verify .nojekyll file** exists

### 404 errors?

- Ensure file names match exactly (case-sensitive)
- Check links in `_sidebar.md`
- Verify GitHub Pages is enabled

### Styling issues?

- Check `index.html` for correct CDN links
- Clear browser cache
- Test in incognito mode

## Best Practices

✅ **DO:**
- Write clear, concise documentation
- Include code examples
- Update docs with code changes
- Preview locally before pushing
- Keep sidebar organized

❌ **DON'T:**
- Commit large images (optimize first)
- Break existing links
- Forget to update sidebar
- Use absolute GitHub URLs in links

## Resources

- [Docsify Documentation](https://docsify.js.org/)
- [GitHub Pages Documentation](https://docs.github.com/pages)
- [Markdown Guide](https://www.markdownguide.org/)

## Questions?

- Open an issue for documentation bugs
- Suggest improvements via pull requests
- Discuss in GitHub Discussions
