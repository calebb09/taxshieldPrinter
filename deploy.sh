#!/bin/bash
echo "🚀 Starting deployment..."

# Go to repo
cd /home/codeopdq/repositories/taxshieldPrinter || exit

# Reset local changes
git reset --hard

# Pull latest from main branch
git pull origin main2

# Copy files to public_html (or symlink)
rsync -av --exclude='.git' . /home/codeopdq/taxshield.codeopia.dev/

echo "✅ Deployment complete!"
