#!/bin/bash

# Default values
version_suffix=""
version_override=""
plugin_dir="$(dirname "$(realpath "$0")")"
# Derive the plugin name from the repo's .plg files, NOT the directory name.
# In CI the repo is bind-mounted at /workspace, so the directory basename would be
# "workspace" instead of "easy-rsync". The .plg filenames are the stable source of
# truth: the stable .plg is the one that is not *.beta.plg, and the plugin name is
# its filename without the ".plg" extension (e.g. easy.rsync).
plg_filepath=$(find "$plugin_dir" -maxdepth 1 -name '*.plg' ! -name '*.beta.plg' | head -n1)
plg_beta_filepath=$(find "$plugin_dir" -maxdepth 1 -name '*.beta.plg' | head -n1)
if [[ -z "$plg_filepath" ]]; then
  echo "Error: no stable .plg file found in $plugin_dir"; exit 1
fi
plugin_name=$(basename "$plg_filepath" .plg) # e.g. easy.rsync
repo_name="${plugin_name//./-}" # GitHub repo uses dashes where the plugin uses dots
accept_flag=false
beta_flag=false
unraidHost=""
dry_run=false

# Function to display usage/help
usage() {
  echo "Usage: $0 [-v <suffix> | -V <version>] [-p <plg-filepath>] [-d] [-y] [-u <unraid-host>] [-b] [-h]"
  echo
  echo "This script builds a package from the source directory and optionally updates a .plg file."
  echo "By default, it searches for a .plg file in the plugin's root directory with the same name as its parent directory."
  echo
  echo "Options:"
  echo "  -v <suffix>    Specify a version suffix to append to today's date (e.g., 'a' -> '2026.05.30a')."
  echo "  -V <version>   Use the given string verbatim as the version (e.g., '2026.05.28.b1'). Mutually exclusive with -v."
  echo "                 Intended for CI where the git tag is the version and the runner's clock cannot be trusted."
  echo "  -p <filepath>  Use a specific .plg file instead of searching. This will replace the md5 hash in the .plg file."
  echo "  -d             Dry Run: Do not make any changes to files or directories."
  echo "  -y             Accept mode: Skip all confirmations and proceed without user input."
  echo "  -u <hostname>  Specifies an Unraid host where the archive will be sent. The script assumes this host is accessible via rsync."
  echo "  -b             Beta build. Adds beta tag to pages and plugin name"
  echo "  -h             Display this help message and exit."
  echo
  echo "Example usage:"
  echo "  $0 -v b                       # Builds today's date with 'b' suffix"
  echo "  $0 -V 2026.05.28.b1 -b        # Builds verbatim beta version (CI path)"
  echo "  $0 -p /path/to/myplugin.plg   # Use a specific .plg file to update"
  echo "  $0 -d                         # Performs a dry run without making any changes to the filesystem."
  echo
}

# Parse command-line options
while getopts ":v:V:p:u:dybh" opt; do
  case ${opt} in
    v)
      version_suffix="$OPTARG"
      ;;
    V)
      version_override="$OPTARG"
      ;;
    p)
      plg_filepath=$(realpath "$OPTARG")
      ;;
    u)
      unraidHost="$OPTARG"
      ;;
    d)
      dry_run=true
      ;;
    y)
      accept_flag=true
      ;;
    b)
      beta_flag=true
      ;;
    h)
      usage
      exit 0
      ;;
    \?)
      usage
      exit 1
      ;;
  esac
done

if [[ -n "$version_suffix" && -n "$version_override" ]]; then
  echo "Error: -v and -V are mutually exclusive."
  exit 1
fi

# Shift to remove the parsed options
shift $((OPTIND - 1))

# Generate the version string
if [[ -n "$version_override" ]]; then
  version="$version_override"
else
  version_date=$(date +"%Y.%m.%d")
  if [[ -n "$version_suffix" ]]; then
    version=$version_date$version_suffix
  else
    version=$version_date
  fi
fi

# Beta flag handling
if [[ "$beta_flag" == true ]]; then
  if ! [[ "$version" =~ ^[0-9]{4}\.[0-9]{2}\.[0-9]{2}\.b[0-9]+$ ]]; then
    echo "Error: Beta version '$version' must match YYYY.MM.DD.b<N> (e.g. 2026.05.30.b1)."
    echo "       Pass an explicit version with -V (e.g. -V 2026.05.30.b1), or use -v with a '.b<N>' suffix."
    exit 1
  fi
  plugin_name="${plugin_name}.beta"
  plg_filepath="$plg_beta_filepath"
fi

if [ "$EUID" -ne 0 ]; then
    echo "Please run as root"
    exit 1
fi

# Skip confirmation if force flag is enabled
if [[ "$accept_flag" == false ]]; then
  # Display configuration
  echo -e "Plg filepath: \t'$plg_filepath'"
  echo -e "Plugin name: \t'$plugin_name'"
  echo -e "Version string: '$version'"

  read -r -p "Are these correct? (y/Y to proceed): " user_input
  if [[ "$user_input" != "y" && "$user_input" != "Y" ]]; then
    echo "Exiting."
    exit 1
  fi
else
  echo "Accept mode enabled. Skipping confirmation."
fi
echo "Proceeding..."
echo

# Set paths based on the plugin name and developer directory
src_dir="$plugin_dir/source"
archive_dir="$plugin_dir/archive"

# Generate a unique temporary directory
tmpdir="$plugin_dir/tmp/tmp.$(( $RANDOM * 19318203981230 + 40 ))"
trap "rm -rf $tmpdir" EXIT

# Create necessary directories
mkdir -p "$tmpdir/usr/local/emhttp/plugins/$plugin_name"
mkdir -p "$archive_dir"

# Navigate to the source directory
cd "$src_dir" || { echo "Source directory $src_dir does not exist."; exit 1; }

# Ensure permissions and copy files to the temporary directory
chmod 0755 -R "$src_dir"
# Cannot put in quotes or else it fails
cp --parents -f $(find . -type f ! \( -iname "pkg_build.sh" -o -iname "sftp-config.json" \) ) \
    "$tmpdir/usr/local/emhttp/plugins/$plugin_name/"
chmod 0755 -R "$tmpdir"
#chown root:root -R "$tmpdir"
chmod 0755 "$plugin_dir"

# Beta flag handling
if [[ "$beta_flag" == true ]]; then
  # rename .page files
  find "$tmpdir/usr/local/emhttp/plugins/$plugin_name/" \
  -maxdepth 1 -type f -name "*.page" -exec bash -c 'for f; do mv -- "$f" "${f%.page}.Beta.page"; done' _ {} +

  # replace plugin name within file
  find "$tmpdir/usr/local/emhttp/plugins/$plugin_name/" \
  -type f -name "*.Beta.page" -exec sed -i 's/Menu="EasyRsync:/Menu="EasyRsync.Beta:/g' {} +

  # replace include filepath
  find "$tmpdir/usr/local/emhttp/plugins/$plugin_name/" \
  -type f -name "*.Beta.page" -exec sed -i 's/\/easy.rsync\//\/easy.rsync.beta\//g' {} +

  # replace appname in settings
  find "$tmpdir/usr/local/emhttp/plugins/$plugin_name/" \
  -type f -name "ERSettings.php" -exec sed -i "s/\$appName = 'easy.rsync'/\$appName = 'easy.rsync.beta'/g" {} +
fi

# If dry run, only create archive package in tmp directory
if [[ "$dry_run" == true ]]; then
  archive_file="$plugin_dir/tmp/$plugin_name-${version}.txz"
else
  archive_file="$archive_dir/$plugin_name-${version}.txz"
fi
echo "Archive File:"
echo "$archive_file"
echo

# Create the package
cd "$tmpdir" || { echo "Temporary directory $tmpdir does not exist."; exit 1; }
if ! makepkg --linkadd y --chown y "$archive_file" ; then
  echo "Failed to make package. Exiting."
  exit 1
fi

# Output the MD5 checksum of the package
hash=$(md5sum "$archive_file" | awk '{print $1}')
# echo "MD5:"
# echo "$hash"

# Replace hash and suffix in .plg file if specified
if [[ -n "$plg_filepath" && "$dry_run" == false ]]; then
  if [[ -w "$plg_filepath" ]]; then
    sed -i "s|<!ENTITY md5          \".*\">|<!ENTITY md5          \"$hash\">|" "$plg_filepath"

    sed -i "s|<!ENTITY repoName     \".*\">|<!ENTITY repoName     \"$repo_name\">|" "$plg_filepath"
    sed -i "s|<!ENTITY pluginName   \".*\">|<!ENTITY pluginName   \"$plugin_name\">|" "$plg_filepath"
    sed -i "s|<!ENTITY version      \".*\">|<!ENTITY version      \"$version\">|" "$plg_filepath"

    if [[ "$beta_flag" == true ]]; then
      sed -i "s|<!ENTITY gitBranch    \".*\">|<!ENTITY gitBranch    \"dev\">|" "$plg_filepath"
    else
      sed -i "s|<!ENTITY gitBranch    \".*\">|<!ENTITY gitBranch    \"main\">|" "$plg_filepath"
    fi

    if [[ -z "$unraidHost" ]]; then # unraid host not set, package will be downloaded from GitHub releases
      sed -i "s|^<!-- <URL>\&releaseLocation;/\&pluginName;-\&version;\.txz</URL> -->|<URL>\&releaseLocation;/\&pluginName;-\&version;\.txz</URL>|" "$plg_filepath"
    else # unraid host set, package is pre-deployed via rsync — URL is unreachable, comment it out
      sed -i "s|^<URL>\&releaseLocation;/\&pluginName;-\&version;\.txz</URL>|<!-- <URL>\&releaseLocation;/\&pluginName;-\&version;\.txz</URL> -->|" "$plg_filepath"
      sed -i "s|<!ENTITY gitBranch    \".*\">|<!ENTITY gitBranch    \"dev\">|" "$plg_filepath"

      rsync "$archive_file" "$unraidHost:/boot/config/plugins/$plugin_name/"
      rsync "$plg_filepath" "$unraidHost:/boot/"
    fi
  fi
fi

echo "<!ENTITY md5        \"$hash\">"
echo "<!ENTITY version    \"$version\">"
