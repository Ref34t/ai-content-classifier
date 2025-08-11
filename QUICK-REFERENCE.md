# Quick Reference: Git + SVN Workflow

## 🚀 **Daily Development**
```bash
# Work in Git as normal
git checkout -b feature/new-feature
# ... develop and test ...
git commit -am "Add new feature"
git push origin feature/new-feature
# Create PR, review, merge to main
```

## 📦 **Release to WordPress.org**
```bash
# One command does everything:
./auto-release.sh

# This will automatically:
# ✅ Auto-bump patch version (+1)
# ✅ Update all version references
# ✅ Git commit, tag, and push
# ✅ SVN sync, commit, and tag
# ✅ Live on WordPress.org!
```

## 🔧 **Manual SVN Commands** (if needed)
```bash
# Check SVN status
cd ai-content-classifier-svn && svn status

# Manual sync (use script instead)
rsync -av --exclude='.git' --exclude='node_modules' \
  --exclude='.github' --exclude='tests' \
  ./ ai-content-classifier-svn/trunk/

# Manual SVN commit
cd ai-content-classifier-svn
svn commit -m "Release version 1.2.0" --username mokhaled

# Create SVN tag
svn cp trunk tags/1.2.0
svn commit -m "Tag version 1.2.0" --username mokhaled
```

## 📂 **Important Paths**
- **Git Repo:** `/Users/mokhaled/world/tut/tutland/docs/week2-bootcamp/ai-content-classifier/`
- **SVN Repo:** `/Users/mokhaled/world/tut/tutland/docs/week2-bootcamp/ai-content-classifier/ai-content-classifier-svn/`
- **WordPress.org:** `https://wordpress.org/plugins/ai-content-classifier`
- **SVN URL:** `https://plugins.svn.wordpress.org/ai-content-classifier`

## 🔑 **Credentials** (stored in CLAUDE.md)
- **SVN Username:** mokhaled
- **SVN Password:** svn_PULKANYYkg2LRy08ehvyh3uz4NqQfY6rd44d14ab

## ✅ **Current Status**
- ✅ Plugin live on WordPress.org
- ✅ Version 1.1.5 tagged and released
- ✅ Professional assets committed
- ✅ Git/SVN workflow established
- ✅ Fully automated release system ready

## 🆘 **Emergency Rollback**
```bash
# Rollback WordPress.org to previous version
cd ai-content-classifier-svn
svn cp tags/1.1.4 trunk --force
svn commit -m "Emergency rollback to 1.1.4" --username mokhaled
```

---

**Next Development:** Continue in Git, release to WordPress.org when ready using `./auto-release.sh`