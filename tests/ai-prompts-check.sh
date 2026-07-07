#!/bin/bash
# Quick check that all 4 AI system prompt files contain the profanity/toxicity rule.

FILES=(
    "app/Ai/Agents/AssistantAgent.php"
    "app/Services/OllamaAIService.php"
    "app/Services/GroqAIService.php"
    "app/Services/CloudflareAIService.php"
)

PATTERN="PROFANITY & TOXICITY"
ALL_PASS=true

echo ""
echo "═════════════════════════════════════════════════"
echo "  AI PROMPT FILES — PROFANITY RULE CHECK"
echo "═════════════════════════════════════════════════"
echo ""

for FILE in "${FILES[@]}"; do
    if grep -q "$PATTERN" "$FILE" 2>/dev/null; then
        echo "  ✅ PASS  $FILE — contains profanity rule"
    else
        echo "  ❌ FAIL  $FILE — MISSING profanity rule!"
        ALL_PASS=false
    fi
done

echo ""
if [ "$ALL_PASS" = true ]; then
    echo "  ✅ All 4 AI prompt files contain the profanity/toxicity rule."
else
    echo "  ❌ Some AI prompt files are missing the rule!"
fi
echo "═════════════════════════════════════════════════"
echo ""

if [ "$ALL_PASS" = false ]; then
    exit 1
fi
