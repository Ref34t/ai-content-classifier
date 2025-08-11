#!/bin/bash

# AI Content Classifier - WordPress.org Release Script
# Syncs Git repository to WordPress.org SVN

set -e

# Configuration
PLUGIN_SLUG="ai-content-classifier"
GIT_REPO_PATH=$(pwd)
SVN_REPO_PATH="${GIT_REPO_PATH}/${PLUGIN_SLUG}-svn"
SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}"
SVN_USERNAME="mokhaled"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "ai-content-classifier.php" ]; then
    print_error "Must run from plugin root directory"
    exit 1
fi

# Get version from main plugin file
VERSION=$(grep "Version:" ai-content-classifier.php | awk '{print $3}')
if [ -z "$VERSION" ]; then
    print_error "Could not determine plugin version"
    exit 1
fi

print_status "Preparing release for version: $VERSION"

# Check if Git working directory is clean
if ! git diff-index --quiet HEAD --; then
    print_warning "Git working directory has uncommitted changes"
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Ensure SVN repo exists and is up to date
if [ ! -d "$SVN_REPO_PATH" ]; then
    print_status "Checking out SVN repository..."
    svn co $SVN_URL $SVN_REPO_PATH
else
    print_status "Updating SVN repository..."
    cd $SVN_REPO_PATH && svn update && cd $GIT_REPO_PATH
fi

# Files to exclude from SVN (Git-specific files)
EXCLUDE_LIST=(
    '.git'
    '.github'
    '.claude'
    '.idea'
    '.vscode'
    'node_modules'
    'vendor'
    '.gitignore'
    '.gitattributes'
    'composer.json'
    'composer.lock'
    'package.json'
    'package-lock.json'
    'webpack.config.js'
    'gulpfile.js'
    'Gruntfile.js'
    '.DS_Store'
    'Thumbs.db'
    '*.log'
    'release-to-wordpress.sh'
    'assets-source'
    'assets/*.html'
    'assets/README-ASSETS.md'
    'README.md'
    'CHANGELOG.md'
    'CONTRIBUTING.md'
    'GIT-SVN-WORKFLOW.md'
    'QUICK-REFERENCE.md'
    'phpunit.xml'
    'phpcs.xml'
    'tests'
    'docs'
    '*.swp'
    '*.swo'
    '*.tmp'
    '*.temp'
    'ai-content-classifier-svn'
    '*.md'
)

# Build rsync exclude arguments
EXCLUDE_ARGS=""
for item in "${EXCLUDE_LIST[@]}"; do
    EXCLUDE_ARGS="$EXCLUDE_ARGS --exclude=$item"
done

# Sync files to SVN trunk
print_status "Syncing files to SVN trunk..."
rsync -av --delete $EXCLUDE_ARGS $GIT_REPO_PATH/ $SVN_REPO_PATH/trunk/

# Change to SVN directory
cd $SVN_REPO_PATH

# Add any new files to SVN
print_status "Adding new files to SVN..."
svn add trunk --force --quiet

# Remove deleted files from SVN
print_status "Removing deleted files from SVN..."
svn status | grep '^!' | awk '{print $2}' | xargs -r svn remove

# Show SVN status
print_status "SVN Status:"
svn status

# Ask for confirmation before committing
echo
read -p "Commit changes to SVN trunk? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_status "Committing to SVN trunk..."
    svn commit -m "Update trunk to version $VERSION

Auto-sync from Git repository:
- WordPress.org compliant release
- Security hardened codebase  
- Complete feature set ready for distribution

Version: $VERSION" --username $SVN_USERNAME

    # Ask about creating tag
    echo
    read -p "Create SVN tag for version $VERSION? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_status "Creating SVN tag: $VERSION"
        svn cp trunk tags/$VERSION
        svn commit -m "Tag version $VERSION" --username $SVN_USERNAME
        print_status "✅ Successfully released version $VERSION to WordPress.org!"
    fi
else
    print_warning "Commit cancelled"
fi

# Return to original directory
cd $GIT_REPO_PATH

print_status "Release process complete!"
print_status "WordPress.org Plugin: https://wordpress.org/plugins/$PLUGIN_SLUG"