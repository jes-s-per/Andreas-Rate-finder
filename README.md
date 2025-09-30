# Andreas Rate finder — PHP REST API + HTML/JS Frontend

This project implements a small REST API in PHP with a minimal HTML/JavaScript frontend to interact with it. The backend accepts a payload, transforms it to the required format, calls the remote API, and returns the response.

## Project Structure

- `index.html` — Simple UI to compose a request and display results.
- `main.js` — Frontend logic to call the backend and render responses.
- `api/rates.php` — PHP REST endpoint that validates input, transforms payload, calls the remote API, and returns the result.
- `.gitignore` — Basic ignores for common OS/IDE artifacts.

## Requirements

- PHP 7.4+ (tested with PHP 8.x)
- Internet access for the remote API call

## Running Locally (Windows)

1. Open a terminal in the project root directory.
2. Start PHP's built-in server:
   
   ```bash
   php -S localhost:8000
   ```

3. Open your browser at:
   
   - http://localhost:8000/

The frontend will send requests to `http://localhost:8000/api/rates.php`.

## API

- Endpoint: `POST /api/rates.php`
- Request Body (input model):

```json
{
  "Unit Name": "Standard Room",
  "Arrival": "dd/mm/yyyy",
  "Departure": "dd/mm/yyyy",
  "Occupants": 2,
  "Ages": [12, 10]
}
```

- Notes:
  - `Unit Name` can be one of the known names below or a numeric string containing the Unit Type ID.
  - Known mappings (for testing):
    - `Standard Room` -> `-2147483637`
    - `Family Room` -> `-2147483456`
  - The backend converts arrival/departure to `yyyy-mm-dd` and converts `Ages` into `Guests` with `Age Group` set to `Adult` for ages >= 12 and `Child` otherwise.

- Outgoing payload to remote API (example):

```json
{
  "Unit Type ID": -2147483637,
  "Arrival": "2025-10-01",
  "Departure": "2025-10-05",
  "Guests": [
    { "Age Group": "Adult" },
    { "Age Group": "Child" }
  ]
}
```

- Response:
  - The service proxies the remote API response. If JSON, it returns:

```json
{
  "request": { /* the transformed payload sent to the remote API */ },
  "response": { /* JSON response received from the remote API */ }
}
```

  - If the remote response is not valid JSON, it returns the raw text in `remote_raw` with the remote HTTP status in `remote_status`.

### Example cURL

```bash
curl -X POST http://localhost:8000/api/rates.php \
  -H "Content-Type: application/json" \
  -d '{
    "Unit Name": "Standard Room",
    "Arrival": "01/10/2025",
    "Departure": "05/10/2025",
    "Occupants": 2,
    "Ages": [12, 10]
  }'
```

## Frontend

Open `http://localhost:8000/` and fill in the form:

- Choose a predefined `Unit Name` or leave it blank and enter a numeric `Custom Unit Type ID`.
- Provide `Arrival` and `Departure` in `dd/mm/yyyy` format.
- Set `Occupants` and comma-separated `Ages`. The count of ages must match occupants.

## Quality (Optional for assignment)

- Add this repository to GitHub.
- Configure a SonarCloud project and a GitHub Action to scan on PRs to `main` if desired.
- You can run this code in GitHub Codespaces; the command `php -S 0.0.0.0:8000` will expose the server on port 8000.

## Notes

- CORS is enabled broadly in `api/rates.php` to simplify local development.
- Error handling includes validation on required fields, date parsing, occupants vs ages count, and network errors calling the remote API.

## SonarCloud Test: Setup complete on 2025-09-30.
