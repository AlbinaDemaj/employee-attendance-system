# Prezenca — Frontend (React 19 + Vite)

SPA në React 19, React Router 7 dhe Vite 8.

Udhëzimet e plota të instalimit dhe nisjes ndodhen te
[README-ja kryesore e projektit](../README.md).

```bash
npm install
npm run dev     # http://127.0.0.1:5173
npm run build   # build për prodhim, del te dist/
npm run lint
```

Vite-i i proxy-on `/api` dhe `/storage` te backend-i — origjina caktohet me
`VITE_BACKEND_ORIGIN` (si parazgjedhje `http://127.0.0.1:8000`).
