<?php
$konek = mysqli_connect("localhost", "root", "", "lms");

if (!$konek) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
