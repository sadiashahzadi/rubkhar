'use strict';

const app = require('./src/app');
const port = parseInt(process.env.PORT, 10) || 3000;

app.listen(port, () => {
  console.log(`Rubkhar dev server running at http://localhost:${port}`);
});
