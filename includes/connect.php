<section class="twitter-connect">
  <?php
    if (!empty($_GET['error'])) {
      echo '<p class="error">' . $_GET['error'] . '</p>';
    }
  ?>
  <p class="instruction">Enter your Twitter handle:</p>
  <form action="includes/user.php" method="get">
    <label for="handle" class="at">@</label>
    <input type="search" name="handle" id="handle" class="search" placeholder="username" autofocus>
    <input type="submit" class="go" value="Go">
  </form>
</section>