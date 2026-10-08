## Application rules
- The web app lives in `frontend/`. Use a shared frontend workspace and separate file-based routes for major operations views, so navigation remains shareable.
- The Laravel API lives in `backend/`. The React app reads and updates ushers, projects, and staffing through that API. Public registration, availability, client, and rating links use unguessable tokens.
- Define all interface colors and custom presentation styles in frontend/src/styles.css and use the existing Button and Dialog controls for consistent interaction.
