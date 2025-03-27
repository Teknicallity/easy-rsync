#!/bin/bash

# Default values
version_suffix=""
plugin_dir="$(dirname "$(realpath "$0")")"
plg_filepath=$(find "$plugin_dir" -name "*.plg" -exec realpath {} \;)
repo_name=$(printf '%q\n' "${plugin_dir##*/}") # takes the name from root directory
plugin_name="${repo_name//-/\.}" # replaces dashes with dots
accept_flag=false
unraidHost=""
dry_run=false

# Function to display usage/help
usage() {
  echo "Usage: $0 [-v <suffix>] [-p <plg-filepath>] [-d] [-y] [-u <unraid-host>] [-h]"
  echo
  echo "This script builds a package from the source directory and optionally updates a .plg file."
  echo "By default, it searches for a .plg file in the plugin's root directory with the same name as its parent directory."
  echo
  echo "Options:"
  echo "  -v <suffix>    Specify a version suffix (e.g., 'a')."
  echo "  -p <filepath>  Use a specific .plg file instead of searching. This will replace the md5 hash in the .plg file."
  echo "  -d             Dry Run: Do not make any changes to files or directories."
  echo "  -y             Accept mode: Skip all confirmations and proceed without user input."
  echo "  -u <hostname>  Specifies an Unraid host where the archive will be sent. The script assumes this host is accessible via rsync."
  echo "  -h             Display this help message and exit."
  echo
  echo "Example usage:"
  echo "  $0 -v b                       # Builds a package with 'b' as version suffix"
  echo "  $0 -p /path/to/myplugin.plg   # Use a specific .plg file to update"
  echo "  $0 -d                         # Performs a dry run without making any changes to the filesystem."
  echo
}

# Parse command-line options
while getopts ":v:p:u:dyh" opt; do
  case ${opt} in
    v)
      version_suffix="$OPTARG"
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

# Shift to remove the parsed options
shift $((OPTIND - 1))

if [ "$EUID" -ne 0 ]; then
    echo "Please run as root"
    exit 1
fi

# Generate the version string
version_date=$(date +"%Y.%m.%d")
if [[ -n "$version_suffix" ]]; then
  version=$version_date$version_suffix
else
  version=$version_date
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


    if [[ -z "$unraidHost" ]]; then #unraid host not set, package is in github main
      sed -i "s|^<!-- <URL>\&repoLocation;/archive/\&pluginName;-\&version;\.txz</URL> -->|<URL>\&repoLocation;/archive/\&pluginName;-\&version;\.txz</URL>|" "$plg_filepath"
      sed -i "s|<!ENTITY gitBranch    \".*\">|<!ENTITY gitBranch    \"main\">|" "$plg_filepath"
    else # unraid host set, package should be transferred and use dev branch files
      sed -i "s|^<URL>\&repoLocation;/archive/\&pluginName;-\&version;\.txz</URL>|<!-- <URL>\&repoLocation;/archive/\&pluginName;-\&version;\.txz</URL> -->|" "$plg_filepath"
      sed -i "s|<!ENTITY gitBranch    \".*\">|<!ENTITY gitBranch    \"dev\">|" "$plg_filepath"

      rsync "$archive_file" "$unraidHost:/boot/config/plugins/$plugin_name/"
      rsync "$plg_filepath" "$unraidHost:/boot/"
    fi
  fi
fi

echo "<!ENTITY md5        \"$hash\">"
echo "<!ENTITY version    \"$version\">"
