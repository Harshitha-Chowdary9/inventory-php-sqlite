# Study guide

- Read the two tables: stock answers how many units remain; reservations answers whether a request has already succeeded. Why does request_id need to be unique?
- Follow one HTTP request from router validation to Store::reserve. Explain each prepared statement.
- Why is `quantity >= ?` part of the UPDATE? Why must the idempotency lookup and decrement share a transaction?
- Run the same request twice, then change its quantity. Explain 201, 200 and 409 without calling retries an error.
- Stop and restart the server; show that stock and idempotency records remain.
- Read `/metrics`. A counter grows over time; a gauge can rise or fall. Which signal would you alert on and why?
- Export metrics before and after a large reservation. Run check-alerts and explain its exit codes. This is local monitoring practice, not production operational experience.
- Try adding a test for negative quantities. Sketch a stock-restock endpoint and its permission rules before coding it.
- Sketch how PostgreSQL would change locking and deployment. Do not claim that this lab uses PostgreSQL, AWS or Kotlin.

This is a new assistant-assisted learning lab. Read, run and change it before presenting it as hands-on practice. It does not replace an explanation of your original PHP work.
