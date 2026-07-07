/**
 * Client-side guardrail tests — replicates the logic from FloatingWidget.vue
 * so we can verify it blocks profanity, insults, harassment, and leetspeak
 * without needing a browser/component test harness.
 */

const PASS = '✅ PASS';
const FAIL = '❌ FAIL';
let passed = 0;
let failed = 0;

function assert(condition, label) {
    if (condition) {
        console.log(`${PASS} ${label}`);
        passed++;
    } else {
        console.log(`${FAIL} ${label}`);
        failed++;
    }
}

// ── Normalize message (mirrors FloatingWidget.vue normalizeMessage) ──
function normalizeMessage(message) {
    return message
        .replace(/0/g, 'o')
        .replace(/1/g, 'i')
        .replace(/3/g, 'e')
        .replace(/4/g, 'a')
        .replace(/5/g, 's')
        .replace(/7/g, 't')
        .replace(/8/g, 'b')
        .replace(/@/g, 'a')
        .replace(/\$/g, 's')
        .replace(/\!/g, 'i')
        .replace(/\|/g, 'i');
}

// ── Toxicity patterns (mirrors FloatingWidget.vue toxicityPatterns) ──
const toxicityPatterns = [
    // Swear words and abbreviations (word-boundary)
    /\b(fuck|fck|fkn|wtf|wth|stfu|shit|bullshit|shitty|ass|asshole|bitch|bastard|damn|goddamn|hell|crap|pissed|dick|dickhead|prick|cunt|whore|slut|hoe|motherfucker|mofo|douche|douchebag|jackass|arse|bloody)\b/i,
    // Sloppy match — catches fuck/fck anywhere (inside compound words like "fucking", "motherfcker")
    /(fuck|fck)/i,
    // Insults
    /\b(stupid|dumb|idiot|moron|retard|useless|trash|suck|kys|kill yourself|shut up|annoying|loser)\b/i,
    // Harassment / toxicity
    /\b(bully|harass|threat|hate speech|racist|sexist|creep|weirdo)\b/i,
];

// ── Educational keywords (mirrors FloatingWidget.vue) ──
const educationalKeywords = /\b(assignment|exam|course|lesson|study|learn|class|homework|grade|teacher|professor|school|university|subject|topic|chapter|review|practice|quiz|test|project|research|paper|essay|report|reading|lecture|tutor|academic|science|math|history|literature|english|filipino|physics|chemistry|biology|geography|economics|psychology|philosophy|art|music|drama|exercise|problem|solve|explain|understand|help|question|answer|feedback|score|level|x[pP]|streak|badge|achiev|progress|module|unit|curriculum|syllabus|lesson|discuss|analyze|analysis|evaluate|critique|summarize|define|describe|compare|contrast|outline|diagram|illustrate|interpret|justify|argument|thesis|concept|theory|principle|formula|equation|experiment|lab|observation|data|evidence|source|citation|reference|bibliography|vocabulary|grammar|sentence|paragraph|comprehension|essay|writing|prompt|rubric|score)\b/i;

// ── Check guardrail (mirrors FloatingWidget.vue checkGuardrail) ──
function checkGuardrail(message) {
    const normalized = normalizeMessage(message);

    // Always block toxicity/harassment first
    for (const pattern of toxicityPatterns) {
        if (pattern.test(message) || pattern.test(normalized)) {
            return 'blocked';
        }
    }

    // If the message has educational context, let it through
    if (educationalKeywords.test(message)) {
        return null;
    }

    return null;
}

console.log('\n═════════════════════════════════════════════════');
console.log('  CLIENT-SIDE GUARDRAIL TESTS');
console.log('═════════════════════════════════════════════════\n');

// ── normalizeMessage tests ──
console.log('── normalizeMessage ──\n');

assert(normalizeMessage('sh1t') === 'shit', 'leetspeak 1→i: sh1t → shit');
assert(normalizeMessage('b@stard') === 'bastard', 'leetspeak @→a: b@stard → bastard');
assert(normalizeMessage('d1ck') === 'dick', 'leetspeak 1→i: d1ck → dick');
assert(normalizeMessage('cr4p') === 'crap', 'leetspeak 4→a: cr4p → crap');
assert(normalizeMessage('$lut') === 'slut', 'leetspeak $→s: $lut → slut');
assert(normalizeMessage('5hit') === 'shit', 'leetspeak 5→s: 5hit → shit');
assert(normalizeMessage('h3ll') === 'hell', 'leetspeak 3→e: h3ll → hell');
assert(normalizeMessage('m0th3rfck3r') === 'motherfcker', 'leetspeak 0→o, 3→e');
assert(normalizeMessage('sh!t') === 'shit', 'leetspeak !→i: sh!t → shit');
assert(normalizeMessage('sh|t') === 'shit', 'leetspeak |→i: sh|t → shit');
assert(normalizeMessage('hell0') === 'hello', 'leetspeak 0→o: hell0 → hello');
assert(normalizeMessage('') === '', 'empty string stays empty');
assert(normalizeMessage('hello world') === 'hello world', 'clean message unchanged');
assert(normalizeMessage('what_th3_fck') === 'what_the_fck', 'leetspeak 3→e only, underscores preserved');

// ── checkGuardrail toxicity tests ──
console.log('\n── checkGuardrail — basic profanity ──\n');

assert(checkGuardrail('fuck') !== null, 'blocks "fuck"');
assert(checkGuardrail('bullshit') !== null, 'blocks "bullshit"');
assert(checkGuardrail('asshole') !== null, 'blocks "asshole"');
assert(checkGuardrail('bitch') !== null, 'blocks "bitch"');
assert(checkGuardrail('bastard') !== null, 'blocks "bastard"');
assert(checkGuardrail('cunt') !== null, 'blocks "cunt"');
assert(checkGuardrail('goddamn') !== null, 'blocks "goddamn"');

console.log('\n── checkGuardrail — abbreviations ──\n');

assert(checkGuardrail('wtf') !== null, 'blocks "wtf"');
assert(checkGuardrail('stfu') !== null, 'blocks "stfu"');
assert(checkGuardrail('fkn') !== null, 'blocks "fkn"');
assert(checkGuardrail('kys') !== null, 'blocks "kys"');

console.log('\n── checkGuardrail — insults ──\n');

assert(checkGuardrail('you are stupid') !== null, 'blocks "stupid"');
assert(checkGuardrail('dumb') !== null, 'blocks "dumb"');
assert(checkGuardrail('idiot') !== null, 'blocks "idiot"');
assert(checkGuardrail('loser') !== null, 'blocks "loser"');
assert(checkGuardrail('retard') !== null, 'blocks "retard"');

console.log('\n── checkGuardrail — harassment ──\n');

assert(checkGuardrail('bully') !== null, 'blocks "bully"');
assert(checkGuardrail('harass') !== null, 'blocks "harass"');
assert(checkGuardrail('racist') !== null, 'blocks "racist"');
assert(checkGuardrail('sexist') !== null, 'blocks "sexist"');
assert(checkGuardrail('creep') !== null, 'blocks "creep"');

console.log('\n── checkGuardrail — leetspeak creative spellings ──\n');

assert(checkGuardrail('sh1t') !== null, 'blocks leetspeak "sh1t"');
assert(checkGuardrail('b@stard') !== null, 'blocks leetspeak "b@stard"');
assert(checkGuardrail('d1ck') !== null, 'blocks leetspeak "d1ck"');
assert(checkGuardrail('cr4p') !== null, 'blocks leetspeak "cr4p"');
assert(checkGuardrail('$lut') !== null, 'blocks leetspeak "$lut"');
assert(checkGuardrail('5h1t') !== null, 'blocks leetspeak "5h1t"');
assert(checkGuardrail('m0th3rfck3r') !== null, 'blocks leetspeak "m0th3rfck3r" (caught by sloppy pattern)');
assert(checkGuardrail('what_th3_fck') !== null, 'blocks "what_th3_fck" (underscore variant, caught by sloppy pattern)');

console.log('\n── checkGuardrail — clean messages pass through ──\n');

assert(checkGuardrail('What are my upcoming assignments?') === null, 'allows assignment question');
assert(checkGuardrail('Can you help me with my math homework?') === null, 'allows homework help');
assert(checkGuardrail('Show me my learning progress') === null, 'allows progress question');
assert(checkGuardrail('What exams do I have coming up?') === null, 'allows exam question');
assert(checkGuardrail('Hello, how are you?') === null, 'allows greeting');
assert(checkGuardrail('Thank you for your help') === null, 'allows thanks');
assert(checkGuardrail('I need help with my science project') === null, 'allows science help');

console.log('\n── checkGuardrail — word boundary edge cases ──\n');

assert(checkGuardrail('classification') === null, 'does not flag "classification" (contains "ass")');
assert(checkGuardrail('assumption') === null, 'does not flag "assumption" (contains "ass")');
assert(checkGuardrail('cocktail recipe for class') === null, 'does not flag innocent words');
assert(checkGuardrail('I need help with photosynthesis') === null, 'allows biology question');

console.log('\n── checkGuardrail — profanity in sentences ──\n');

assert(checkGuardrail('This is bullshit') !== null, 'blocks "bullshit" in sentence');
assert(checkGuardrail('You are so dumb') !== null, 'blocks "dumb" in sentence');
assert(checkGuardrail('I hate this fucking class') !== null, 'blocks profanity in sentence (fucking caught by sloppy pattern)');
assert(checkGuardrail('What a shitty day') !== null, 'blocks "shitty" in sentence');

// ── Summary ──
console.log('\n═════════════════════════════════════════════════');
console.log(`  Results: ${passed} passed, ${failed} failed`);
console.log('═════════════════════════════════════════════════\n');

process.exit(failed > 0 ? 1 : 0);
