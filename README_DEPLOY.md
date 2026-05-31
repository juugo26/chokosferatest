<<<<<<< HEAD
Chokosfera — Deployment Guide

This repository contains a small Node/Express backend and static frontend files. Below are common cloud deployment options and quick steps. You must provide any cloud credentials/secrets in your chosen platform.

1) Deploy with Render (recommended quick option)
   - Create a new Web Service in Render
   - Connect your GitHub repo (push this project to a repo first)
   - Build command: `npm ci`
   - Start command: `node server.js`
   - Set environment variable `PORT` if Render doesn't provide it (Render provides it automatically)

2) Deploy with Google Cloud Run (container)
   - Build and push the Docker image:
     ```bash
     docker build -t gcr.io/PROJECT-ID/chokosfera:latest .
     docker push gcr.io/PROJECT-ID/chokosfera:latest
     ```
   - Deploy with Cloud Run:
     ```bash
     gcloud run deploy chokosfera --image gcr.io/PROJECT-ID/chokosfera:latest --platform managed --region REGION --allow-unauthenticated
     ```

3) Deploy with Railway / Fly / Railway:
   - These platforms can detect `package.json` and `Dockerfile`. Connect repo and set `start` script to `node server.js`.

4) GitHub Actions -> Render (example)
   - Use the provided workflow template `.github/workflows/deploy_render.yml` and configure `RENDER_API_KEY` and `RENDER_SERVICE_ID` secrets. See Render docs.

Security notes
 - Set `JWT_SECRET` environment variable on the platform to a strong value.
 - The included users storage is a file (`data/users.json`) — for production, use a real database.

If you want, I can:
 - Push this repo to a new GitHub repository I create instructions for, and add a GitHub Actions workflow that will build and deploy to Render/Cloud Run (you must add secrets), or
 - Create a ready-to-run GitHub Actions workflow for Cloud Run (requires `GCP_SERVICE_ACCOUNT_KEY` secret).
=======
Chokosfera — Deployment Guide

This repository contains a small Node/Express backend and static frontend files. Below are common cloud deployment options and quick steps. You must provide any cloud credentials/secrets in your chosen platform.

1) Deploy with Render (recommended quick option)
   - Create a new Web Service in Render
   - Connect your GitHub repo (push this project to a repo first)
   - Build command: `npm ci`
   - Start command: `node server.js`
   - Set environment variable `PORT` if Render doesn't provide it (Render provides it automatically)

2) Deploy with Google Cloud Run (container)
   - Build and push the Docker image:
     ```bash
     docker build -t gcr.io/PROJECT-ID/chokosfera:latest .
     docker push gcr.io/PROJECT-ID/chokosfera:latest
     ```
   - Deploy with Cloud Run:
     ```bash
     gcloud run deploy chokosfera --image gcr.io/PROJECT-ID/chokosfera:latest --platform managed --region REGION --allow-unauthenticated
     ```

3) Deploy with Railway / Fly / Railway:
   - These platforms can detect `package.json` and `Dockerfile`. Connect repo and set `start` script to `node server.js`.

4) GitHub Actions -> Render (example)
   - Use the provided workflow template `.github/workflows/deploy_render.yml` and configure `RENDER_API_KEY` and `RENDER_SERVICE_ID` secrets. See Render docs.

Security notes
 - Set `JWT_SECRET` environment variable on the platform to a strong value.
 - The included users storage is a file (`data/users.json`) — for production, use a real database.

If you want, I can:
 - Push this repo to a new GitHub repository I create instructions for, and add a GitHub Actions workflow that will build and deploy to Render/Cloud Run (you must add secrets), or
 - Create a ready-to-run GitHub Actions workflow for Cloud Run (requires `GCP_SERVICE_ACCOUNT_KEY` secret).
>>>>>>> origin/darkyami
