#!/bin/sh

# Initialize variables
INI_FILE=""
OVERWRITE_FILE=""

# 1. Parse Command Line Arguments
while [ "$#" -gt 0 ]; do
    case "$1" in
        --php-ini-file)
            INI_FILE="$2"
            shift 2
            ;;
        --overwrites)
            OVERWRITE_FILE="$2"
            shift 2
            ;;
        *)
            echo "Error: Unknown parameter $1"
            echo "Usage: $0 --php-ini-file <path> --overwrites <path>"
            exit 1
            ;;
    esac
done

# 2. Validate Inputs
if [ -z "$INI_FILE" ] || [ -z "$OVERWRITE_FILE" ]; then
    echo "Error: Missing arguments. (--php-ini-file and --overwrites are required)"
    echo "Usage: $0 --php-ini-file <path> --overwrites <path>"
    exit 1
fi

if [ ! -f "$INI_FILE" ]; then
    echo "Error: Target file '$INI_FILE' not found."
    exit 1
fi

if [ ! -f "$OVERWRITE_FILE" ]; then
    echo "Error: Overwrites file '$OVERWRITE_FILE' not found."
    exit 1
fi

# 3. Create a temporary working file (Transactional approach)
# We work on this file. If anything fails, we simply don't move it to the original.
WORK_FILE="${INI_FILE}.tmp_processing"
cp "$INI_FILE" "$WORK_FILE" || { echo "Error: Could not create temporary work file."; exit 1; }

# 4. Process Overwrites
echo "Processing changes..."

while read -r line || [ -n "$line" ]; do
    # Clean the line: remove '' and surrounding whitespace
    clean_line=$(echo "$line" | sed 's/\*\] //g' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')

    # Skip empty lines or comments
    case "$clean_line" in
        ""|\#*|\;*) continue ;;
    esac

    # Extract Key and Value
    key=$(echo "$clean_line" | cut -d'=' -f1 | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
    value=$(echo "$clean_line" | cut -d'=' -f2- | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')

    if [ -z "$key" ]; then continue; fi

    # Check if key exists (active or commented out) in the WORK_FILE
    # Regex looks for: Start of line -> optional ; -> optional space -> key -> optional space -> =
    if grep -qE "^[;[:space:]]*$key[[:space:]]*=" "$WORK_FILE"; then
        # Replace existing line (using a temp intermediate for portability)
        sed "s|^[;[:space:]]*$key[[:space:]]*=.*|$key = $value|" "$WORK_FILE" > "${WORK_FILE}.sed"
        
        # Check if sed succeeded
        if [ $? -ne 0 ]; then
            echo "Error: Failed to replace key '$key'. Aborting."
            rm "$WORK_FILE" "${WORK_FILE}.sed" 2>/dev/null
            exit 1
        fi
        mv "${WORK_FILE}.sed" "$WORK_FILE"
    else
        # Key not found, append to end
        echo "" >> "$WORK_FILE"
        echo "$key = $value" >> "$WORK_FILE"
    fi

done < "$OVERWRITE_FILE"

# 5. Final Commit
# Overwrite the original file with the modified work file
mv "$WORK_FILE" "$INI_FILE" || { echo "Error: Failed to overwrite original file."; exit 1; }

echo "Success: $INI_FILE updated successfully."