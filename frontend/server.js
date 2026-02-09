
require("dotenv").config();
const mysql = require("mysql2/promise");
const express = require("express");
const app = express();
const port = process.env.PORT;
app.use(express.json());

const db = mysql.createPool({
  host: "localhost",
  user: "root",
  password: "14101504",
  database: "payment_db",
});

app.post("/billings", async (req, res) => {
  const { student_id, term, total_fees, total_discounts = 0 } = req.body;

  if (!student_id || !term || !total_fees) {
    return res.status(400).json({ error: "Missing required fields" });
  }

  try {
    const [result] = await db.query(
      `INSERT INTO billings (student_id, term, total_fees, total_discounts, balance)
       VALUES (?, ?, ?, ?, ?)`,
      [
        student_id,
        term,
        total_fees,
        total_discounts,
        total_fees - total_discounts,
      ],
    );

    res.status(201).json({
      message: "Billing created successfully",
      billing_id: result.insertId,
    });
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: "Internal Server Error" });
  }
});

app.post("/payments", async (req, res) => {
  const { billing_id, amount, method, reference_no, paid_at } = req.body;

  if (!billing_id || !amount || !method || !reference_no || !paid_at) {
    return res.status(400).json({ error: "All fields are requried" });
  }

  const conn = await db.getConnectionm();

  try {
    await conn.beginTransaction();

    cosnt[billings] = await conn.query(
      "SELECT * FROM billings WHERE billing_id = ? FOR UPDATE",
      [billing_id],
    );

    if (billings.length === 0) {
      await conn.rollback();
      return res.status(404).json({ error: "Billing record not found" });
    }

    const currentBalance = billings[0].balance;

    if (amount > currentBalance) {
      await conn.rollback();

      return res
        .status(400)
        .json({ error: "Payment amount exceeds current balance" });
    }

    const newBalance = currentBalance - amount;
    const newStatus = newBalance === 0 ? "Paid" : "Partial";

    await conn.query(
      "UPDATE billings SET balance = ? status = ? WHERE billing_id =?",
      [newBalance, newStatus, billing_id],
    );

    await conn.commit();

    res.status(201).json({
      message: "Billing Recoreded Successfully",
      payment_id: paymentResult.insertId,
      new_balance: newBalance,
      status: newStatus,
    });
  } catch (error) {
    console.error("Error processing payment:", error);
    res.status(500).json({ error: "An error has Occured" });
  }
});

app.listen(3000, () => {
  console.log("Server running on port 3000");
});
