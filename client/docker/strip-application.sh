#!/bin/sh
# Remove version control, local secrets, developer documentation, and build
# configuration from the application copy before it is compiled.
set -eu

root=${1:?application root required}
case "$root" in
    /|/var|/var/www|"")
        echo "ERROR: refusing to strip application root '$root'." >&2
        exit 1
        ;;
esac

if [ ! -d "$root" ]; then
    echo "ERROR: application root '$root' is not a directory." >&2
    exit 1
fi

cd "$root"

rm -rf \
    .git \
    .github \
    docker \
    client \
    docs \
    scripts \
    api \
    backups \
    node_modules \
    vendor

rm -f \
    .env \
    .env.save \
    .env.local \
    .env.production \
    .gitignore \
    auth.json \
    docker-compose.yml \
    docker-compose.yaml \
    Dockerfile \
    Docker_WSL_Installation_Guide.txt \
    README.md \
    ZRA_GO_LIVE.md \
    zra_setup_guide.md \
    output.php \
    "git commands" \
    TRAINING_MANUAL.docx \
    public/api/curl.md \
    public/User_Manual.html \
    public/Scenario_Guide.html \
    "public/smartTrade-POS-Pro - Comprehensive User Guide.pdf" \
    app/Actions/src_pos.txt \
    app/Views/templates_pos.txt

# .env.example and any other dotenv files (.env is not a glob here).
find "$root" -maxdepth 1 -type f -name '.env*' -delete

# Accidental secret or source-fragment filenames that have shown up in the tree.
find "$root" -maxdepth 1 -type f \( -name '_token_*' -o -name 'ession_status*' \) -delete

# Developer markdown. Keep the two manuals the running application opens.
find "$root" -maxdepth 1 -type f -name '*.md' \
    ! -name 'user_guide.md' \
    ! -name 'TRAINING_MANUAL.md' \
    -delete

echo "Stripped git history, local env files, documentation, and build artifacts from ${root}."
