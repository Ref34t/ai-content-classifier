# Git + SVN Workflow Guide
## Managing Development (Git) and Distribution (WordPress.org SVN)

## 🎯 **Strategy Overview**

### **Git Repository** (Primary Development)
- **Purpose:** Development, collaboration, version control
- **Location:** GitHub, GitLab, or Bitbucket
- **Workflow:** Feature branches, pull requests, continuous integration
- **Contains:** Source code, tests, documentation, build tools

### **SVN Repository** (WordPress.org Distribution)
- **Purpose:** WordPress.org plugin directory distribution only
- **Location:** `https://plugins.svn.wordpress.org/ai-content-classifier`
- **Workflow:** Linear commits, stable releases only
- **Contains:** Production-ready code, WordPress.org assets

## 📁 **Repository Structure**

### **Git Repository Structure (Recommended)**
```
ai-content-classifier/                 # Git repo root
├── .github/
│   ├── workflows/                     # GitHub Actions CI/CD
│   └── ISSUE_TEMPLATE.md
├── src/                               # Source code (or root level)
│   ├── admin/
│   ├── includes/
│   ├── assets/
│   ├── templates/
│   └── ai-content-classifier.php
├── tests/                             # PHPUnit tests
├── docs/                              # Documentation
├── assets-source/                     # Source files for banners/icons
│   ├── banner.psd
│   ├── icon.ai
│   └── screenshots/
├── .gitignore                         # Git ignore rules
├── .gitattributes
├── composer.json                      # PHP dependencies
├── package.json                       # Node.js build tools
├── webpack.config.js                  # Asset compilation
├── release-to-wordpress.sh            # Release automation
├── README.md                          # Development documentation
├── CHANGELOG.md                       # Version history
└── CONTRIBUTING.md                    # Contributor guidelines
```

### **SVN Repository Structure (WordPress.org)**
```
ai-content-classifier-svn/             # SVN repo root
├── assets/                            # WordPress.org assets
│   ├── banner-1544x500.png
│   ├── banner-772x250.png
│   ├── icon-256x256.png
│   ├── icon-128x128.png
│   ├── screenshot-1.png
│   ├── screenshot-2.png
│   ├── screenshot-3.png
│   └── screenshot-4.png
├── trunk/                             # Current release
│   ├── admin/
│   ├── includes/
│   ├── assets/
│   ├── templates/
│   ├── tests/
│   ├── ai-content-classifier.php
│   ├── readme.txt
│   ├── license.txt
│   └── uninstall.php
├── tags/                              # Version history
│   ├── 1.0.0/
│   ├── 1.1.0/
│   └── 1.1.4/
└── branches/                          # Rarely used
```

## 🔄 **Development Workflow**

### **1. Daily Development (Git)**
```bash
# Create feature branch
git checkout -b feature/new-template-system

# Develop and test
# ... make changes ...

# Commit changes
git add .
git commit -m "Add advanced template system with variables"

# Push feature branch
git push origin feature/new-template-system

# Create pull request for code review
# Merge to main after review
```

### **2. Preparing for Release (Git)**
```bash
# Switch to main branch
git checkout main
git pull origin main

# Update version numbers
# - Update version in ai-content-classifier.php header
# - Update version in readme.txt stable tag
# - Update CHANGELOG.md

# Commit version bump
git commit -am "Bump version to 1.2.0"

# Create Git tag
git tag v1.2.0
git push origin main
git push origin v1.2.0
```

### **3. Release to WordPress.org (Git → SVN)**

#### **Option A: Automated Release (Recommended)**
```bash
# Use the release script
./release-to-wordpress.sh
```

#### **Option B: Manual Release**
```bash
# Sync Git to SVN trunk (excluding development files)
rsync -av --exclude='.git' --exclude='node_modules' \
  --exclude='.github' --exclude='tests' \
  --exclude='docs' --exclude='*.md' \
  /path/to/git/repo/ /path/to/svn/trunk/

# Commit to SVN
cd ai-content-classifier-svn
svn add trunk --force
svn commit -m "Release version 1.2.0" --username mokhaled

# Create SVN tag
svn cp trunk tags/1.2.0
svn commit -m "Tag version 1.2.0" --username mokhaled
```

## 📋 **File Management Rules**

### **Files in Git Only (Never SVN)**
```
.git/                    # Git metadata
.github/                 # GitHub Actions, templates
node_modules/            # NPM packages
vendor/                  # Composer packages
tests/                   # Unit tests (optional in SVN)
docs/                    # Development documentation
assets-source/           # Source design files
.gitignore
.gitattributes
composer.json
package.json
webpack.config.js
README.md               # Development readme
CHANGELOG.md
CONTRIBUTING.md
release-to-wordpress.sh
```

### **Files in SVN Only**
```
assets/                 # WordPress.org directory assets
  banner-*.png
  icon-*.png
  screenshot-*.png
readme.txt             # WordPress.org readme (not README.md)
```

### **Files in Both (Synced Git → SVN)**
```
admin/
includes/
templates/
assets/css/
assets/js/
ai-content-classifier.php
license.txt
uninstall.php
```

## 🛠 **Automation Setup**

### **GitHub Actions CI/CD** (`.github/workflows/test.yml`)
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 7.4
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: ./vendor/bin/phpunit
      - name: WordPress Plugin Check
        run: wp plugin check . --format=json
```

### **Release Automation** (`.github/workflows/release.yml`)
```yaml
name: Release to WordPress.org

on:
  release:
    types: [created]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Deploy to WordPress.org
        uses: 10up/action-wordpress-plugin-deploy@stable
        env:
          SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
          SVN_USERNAME: mokhaled
          SLUG: ai-content-classifier
```

## 🔒 **Security Considerations**

### **Credentials Management**
```bash
# Store SVN password securely
echo "svn_PULKANYYkg2LRy08ehvyh3uz4NqQfY6rd44d14ab" > ~/.svn-password
chmod 600 ~/.svn-password

# Use in scripts
SVN_PASSWORD=$(cat ~/.svn-password)
```

### **Sensitive Files**
```gitignore
# .gitignore - Never commit these
.env
config.local.php
*.log
.DS_Store
Thumbs.db
ai-content-classifier-svn/    # Don't commit SVN repo to Git
```

## 📊 **Best Practices**

### **Version Management**
- **Git tags:** `v1.2.0` (with 'v' prefix)
- **SVN tags:** `1.2.0` (no prefix)
- **Semantic versioning:** MAJOR.MINOR.PATCH
- **Keep versions synchronized** between Git and SVN

### **Commit Messages**
```bash
# Git commits (detailed)
git commit -m "Add template variable system

- Support for {{variable}} syntax in templates
- Add variable replacement engine
- Include template preview functionality
- Add unit tests for variable parsing

Fixes #123"

# SVN commits (concise)
svn commit -m "Release version 1.2.0 - Add template variable system"
```

### **Testing Before Release**
```bash
# Test in Git before SVN release
composer install
./vendor/bin/phpunit
wp plugin check . --format=json

# Verify WordPress.org compliance
wp plugin check . --checks=plugin_repo
```

## 🚀 **Release Checklist**

### **Pre-Release (Git)**
- [ ] All tests passing
- [ ] Code review completed
- [ ] Version numbers updated
- [ ] CHANGELOG.md updated
- [ ] WordPress Plugin Check passes
- [ ] Git tag created

### **WordPress.org Release (SVN)**
- [ ] Files synced to SVN trunk
- [ ] Assets updated (if changed)
- [ ] SVN commit completed
- [ ] SVN tag created
- [ ] Plugin directory updated (24-72h)

### **Post-Release**
- [ ] Test installation from WordPress.org
- [ ] Verify assets display correctly
- [ ] Monitor for user feedback
- [ ] Plan next development cycle

## 🆘 **Emergency Procedures**

### **Rollback Release**
```bash
# In SVN - rollback trunk to previous version
cd ai-content-classifier-svn
svn cp tags/1.1.4 trunk --force
svn commit -m "Rollback to version 1.1.4 - Critical bug fix"
```

### **Hotfix Workflow**
```bash
# Git - create hotfix branch
git checkout v1.2.0
git checkout -b hotfix/critical-security-fix

# Fix issue, test, commit
git commit -m "Fix critical security vulnerability"

# Create hotfix tag
git tag v1.2.1
git push origin hotfix/critical-security-fix
git push origin v1.2.1

# Release immediately to WordPress.org
./release-to-wordpress.sh
```

This workflow ensures clean separation between development (Git) and distribution (SVN) while maintaining sync between both repositories.