#!/bin/bash

# Default values
RUN_MODE=false
INPUT_FILE="example.yml"  # Default filename
API_KEY="[setfrom1p]"

# Parse flags
while [[ "$#" -gt 0 ]]; do
    case "$1" in
        --run)
            RUN_MODE=true
            echo "Execution mode enabled: Commands will be executed."
            ;;
        --file)
            INPUT_FILE="$2"
            echo "Using input file: $INPUT_FILE"
            shift  # Skip the next argument (the filename)
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--run] [--file <filename>]"
            exit 1
            ;;
    esac
    shift
done

if ! $RUN_MODE; then
    echo "Dry-run mode: Commands will only be displayed."
fi
echo ""

# Check if the input file exists
if [[ ! -f "$INPUT_FILE" ]]; then
    echo "Error: The file '$INPUT_FILE' does not exist."
    exit 1
fi

# Check if the input file is empty
if [[ ! -s "$INPUT_FILE" ]]; then
    echo "Error: The file '$INPUT_FILE' is empty."
    exit 1
fi

# Check if the input file contains valid YAML content
if ! yq eval . "$INPUT_FILE" &>/dev/null; then
    echo "Error: The file '$INPUT_FILE' is not a valid YAML file or is empty."
    exit 1
fi

# Initialize command counter
COMMAND_COUNT=0

# Process each entry in input.yml, convert it to JSON, and generate commands
while IFS= read -r obj; do
    # Extract the basic fields (lookup_key and name) from the current object
    lookup_key=$(echo "$obj" | jq -r '.lookup_key')
    name=$(echo "$obj" | jq -r '.name')

    # Start building the command as an array
    cmd=("stripe" "entitlements" "features" "create")
    cmd+=("--lookup-key=$lookup_key")
    cmd+=("--name=$name")
    cmd+=("--api-key=$API_KEY")

    # Check if the object contains metadata and process it
    metadata=$(echo "$obj" | jq -c '.metadata // {}')
    if [[ "$metadata" != "{}" ]]; then
        # Loop through metadata entries and append them to the command
        while IFS= read -r entry; do
            cmd+=("-d" \"$entry\")
        done < <(jq -r 'to_entries[] | "metadata[" + .key + "]=" + (.value|tostring)' <<< "$metadata")
    fi

    # Increment command counter
    ((COMMAND_COUNT++))

    # Execute the command if run mode is enabled, otherwise just print it
    if $RUN_MODE; then
        eval "$(echo "${cmd[@]}")"
    else
        echo "${cmd[@]}" # Pretty-print for dry-run mode
        echo ""  # Print a blank line between commands for readability
    fi
done < <(yq eval -o=json '.' "$INPUT_FILE" | jq -c '.[]')

# Provide feedback on execution
if $RUN_MODE; then
    echo ""
    echo "Execution completed: $COMMAND_COUNT commands were executed."
else
    echo "Dry-run completed: $COMMAND_COUNT commands were generated."
fi
