<style>
  #loading-spinner {
    display: none;
    text-align: center;
    margin-top: 15px;
  }
  .spinner-border {
    width: 2rem;
    height: 2rem;
    border: 0.25em solid #ccc;
    border-top: 0.25em solid #007bff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 10px;
  }
  @keyframes spin {
    100% { transform: rotate(360deg); }
  }
</style>


                    <div id="loading-spinner">
                      <div class="spinner-border"></div>
                      <p class="mt-2 font-weight-bold">⏳ Wait for the Validation...</p>
                    </div>