---
paths:
  - 'resources/views/**/*.blade.php'
---

# Views

## Never mix inline @php(...) with @php...@endphp blocks
Blade stores raw PHP blocks with the regex /(?<!@)@php(.*?)@endphp/s, so an inline @php($x = ...) token pairs with the FIRST later @endphp in the same file, wrapping everything in between as one uncompiled raw block (HTML directives like @if/{{ }} silently stay literal, and runtime errors look unrelated, e.g. "Undefined variable" pointing far from the inline call). In any file containing an @endphp block, always assign via the block form: @php $x = ...; @endphp.
