<!-- LOVABLE:BEGIN -->
> [!IMPORTANT]
> This project is connected to [Lovable](https://lovable.dev). Avoid rewriting
> published git history — force pushing, or rebasing/amending/squashing commits
> that are already pushed — as it rewrites history on Lovable's side and the
> user will likely lose their project history.
>
> Commits you push to the connected branch sync back to Lovable and show up in
> the editor, so keep the branch in a working state.
<!-- LOVABLE:END -->

## Application rules
- Use a shared frontend workspace and separate file-based routes for major operations views, so navigation remains shareable.
- Keep demonstration records in a browser-safe module and updates in React context without persistence, because this app is a frontend-only preview.
- Define all interface colors and custom presentation styles in src/styles.css and use the existing Button and Dialog controls for consistent interaction.
