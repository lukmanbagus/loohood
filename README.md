![LooHood Logo](https://loohood.web.id/wp-content/uploads/2026/01/icon2-2048x1130.webp)

# LooHood

Loohood is a WordPress plugin that lets you use WordPress only as a local content editor, not as a public-facing server.

You write content in WordPress using LocalWP.
Loohood exports it into static files, pushes them to GitHub, and lets Cloudflare serve them globally.

WordPress never touches the internet.
Your site stays fast, secure, and simple.

## Features

- ✅ Automatic setup wizard (4 simple steps)
- ✅ Automatically create a private GitHub repository
- ✅ Automatically clone the repository to `/wp-content/uploads/[repo_name]`
- ✅ Automatically create a "Coming Soon" `index.html`, then commit & push
- ✅ Automatically create a Cloudflare Pages project
- ✅ Automatically connect GitHub & Cloudflare
- ✅ One-click "Push to Live" from the dashboard
- ✅ Convert all WordPress content (pages & posts) to static HTML
- ✅ Export assets (themes, uploads) alongside pages
- ✅ Minimal configuration — only requires GitHub & Cloudflare tokens

## Usage Flow

1. **Install & Activate** plugin
2. **Setup Wizard** opens automatically:
   - Step 1: Enter GitHub token
   - Step 2: Create a private repo (auto-clone + `index.html` + commit/push)
   - Step 3: Enter Cloudflare token
   - Step 4: Create a Cloudflare Pages project (connected to the repo)
3. **Push to Live** anytime with one click from the dashboard

## Requirements

- WordPress 5.0 or newer
- PHP 7.4 or newer
- GitHub Personal Access Token with permissions: `repo`, `workflow`
- Cloudflare API Token with permission: `Account > Cloudflare Pages > Edit`

## Installation

### Manual Installation

1. Download the plugin (zip) or clone this repository
2. Upload the plugin folder to `/wp-content/plugins/`
3. Activate the plugin via the "Plugins" menu in WordPress admin
4. Follow the setup wizard that opens automatically

### Plugin Installation

1. Create a `.zip` file from the plugin folder
2. Upload via WordPress admin > Plugins > Add New > Upload Plugin
3. Activate the plugin
4. Follow the setup wizard that opens automatically

## Setup Wizard Details

### Step 1: Connect GitHub

Enter a GitHub Personal Access Token with permissions:
- `repo` - Full control of private repositories
- `workflow` - Update GitHub Action workflows

**How to create a token:**
1. Open https://github.com/settings/tokens/new
2. Select scopes: repo, workflow
3. Name the token (example: "LooHood")
4. Generate and copy the token

### Step 2: Create GitHub Repository

Choose a repository name (default: `wp-static-[timestamp]`).

The plugin will:
- Create a new private repository
- Clone it to `/wp-content/uploads/[repo_name]`
- Create an `index.html` with "Coming Soon"
- Create an initial commit and push it to GitHub

### Step 3: Connect Cloudflare

Enter a Cloudflare API Token with permission:
- Account > Cloudflare Pages > Edit

**How to create a token:**
1. Open https://dash.cloudflare.com/profile/api-tokens
2. Create token > Custom token
3. Permissions: Account > Cloudflare Pages > Edit
4. Account Resources: Include > All accounts
5. Continue to summary > Create token

### Step 4: Create Cloudflare Pages Project

Choose a project name (default: same as the repo name).

The plugin will:
- Create a new Cloudflare Pages project
- Automatically connect it to the GitHub repository that was created
- Generate the live site URL: `https://[project-name].pages.dev`

## Usage After Setup

### Deploy Manual

1. Open WordPress admin > Static Exporter
2. Click the "Push to Live" button
3. The plugin will export and push to GitHub (Cloudflare Pages deploys from GitHub)
4. The page will auto-refresh after deployment completes

### Links & Configuration

The dashboard shows:
- **GitHub Repository**: Link to the GitHub repo
- **Live Site**: Link to the live site on Cloudflare Pages
- **Configuration Info**: Owner, repo, account ID, project name

### Reset & Disconnect

Click "Reset & Disconnect" to:
- Remove all plugin configuration
- **Warning:** The repository and project created on GitHub & Cloudflare are NOT automatically deleted (delete them manually if needed)

## File Structure

```
wp-static-exporter/
├── wp-static-exporter.php  # Main plugin file
├── uninstall.php             # Clean up on uninstall
├── .gitignore                 # Git ignore rules
├── README.md                  # Documentation
├── templates/
│   ├── inc/
│   │   └── header.php        # Header template
│   ├── wizard-page.php       # Setup wizard page
│   ├── admin-page.php        # Main admin page
│   └── settings-page.php     # Legacy settings page
└── assets/
    └── admin.css            # Admin styles
```

/wp-content/uploads/[repo_name]/

**GitHub token invalid**
- Make sure the token is still valid
- Check permissions: repo, workflow
- Generate a new token if needed

**Cloudflare token invalid**
- Make sure the token is still valid
- Check permission: Account > Cloudflare Pages > Edit
- Generate a new token if needed

**Failed to create repository**
- Make sure the GitHub token has `repo` permission
- Check whether a repository with the same name already exists

**Failed to create Cloudflare project**
- Make sure the Cloudflare token has `Cloudflare Pages > Edit` permission
- Check that the Account ID is filled in

### Deployment Error

**Export failed**
- Make sure `/wp-content/uploads/` is writable (permission 755)
- Check PHP memory limit (256MB minimum recommended)
- Make sure the theme does not produce broken absolute URLs

**GitHub push failed**
- Verify the GitHub token is still valid
- Make sure the token has the correct permissions
- Check that the repository still exists and is accessible

**Cloudflare deployment failed**
- Make sure the Cloudflare Pages project exists
- Verify the Cloudflare API token is valid
- Check that the Account ID is correct
- Make sure Cloudflare Pages is connected to the GitHub repository

**Files are not exported**
- Make sure the post/page status is "publish"
- Check the post is not password protected
- Verify permalink structure in Settings > Permalinks


**Note:** The GitHub repository and Cloudflare Pages project are not automatically deleted. Delete them manually if needed:
- GitHub: Open repository > Settings > Delete repository
- Cloudflare: Open project > Settings > Delete project

## FAQ

**Q: Can I use an existing GitHub repository?**
A: Currently, the plugin only supports creating a new repository. To use an existing repo, edit configuration manually in wp-admin/options.php

**Q: Can I use a custom domain on Cloudflare Pages?**
A: Yes. After setup is complete, you can add a custom domain in the Cloudflare Pages project settings

**Q: How often should I deploy?**
A: Deploy whenever content changes (posts, pages, media). You can also deploy manually anytime from the dashboard

## Support & Contribution

For issues or feature requests, open an issue on the GitHub repository.

## License

GPL v2 or later
