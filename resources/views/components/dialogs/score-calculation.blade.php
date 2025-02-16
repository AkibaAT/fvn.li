<dialog
    wire:ignore.self
    id="score-calculation-modal"
    class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-3xl dark:text-gray-100 backdrop:backdrop-blur-md"
>
    <x-dialog-header title="Score Calculation Process"/>

    <div class="space-y-8 prose dark:prose-invert max-w-none">
        <section>
            <h2 class="text-xl font-bold mb-3">Overview</h2>
            <p class="text-gray-600 dark:text-gray-300">
                Game scores are calculated using a weighted average system that takes into account both the raw ratings
                and the reliability of raters. This process happens in two main steps:
            </p>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3">Step 1: Calculating Rater Weights</h2>
            <p class="text-gray-600 dark:text-gray-300 mb-4">Each rater is assigned a weight based on multiple
                factors:</p>
            <ol class="space-y-6">
                <li>
                    <h3 class="text-base font-semibold mb-2">Rating Distribution Analysis</h3>
                    <p class="text-gray-600 dark:text-gray-300">The system examines how a rater uses the 1-5 rating
                        scale</p>
                </li>
                <li>
                    <h3 class="text-base font-semibold mb-2">Entropy Calculation</h3>
                    <p class="text-gray-600 dark:text-gray-300">A measure of rating diversity is calculated using:</p>
                    <pre class="bg-gray-100 dark:bg-gray-700 p-3 rounded mt-2 text-sm font-mono">
Entropy = -Σ(p * log(p))
where p = probability of each rating</pre>
                </li>
                <li>
                    <h3 class="text-base font-semibold mb-2">Rating Count Impact</h3>
                    <p class="text-gray-600 dark:text-gray-300">A sigmoid function weights raters based on their total
                        ratings:</p>
                    <pre class="bg-gray-100 dark:bg-gray-700 p-3 rounded mt-2 text-sm font-mono">
RatingWeight = 1 / (1 + e^(-0.1 * (total_ratings - minimum_threshold)))</pre>
                </li>
                <li>
                    <h3 class="text-base font-semibold mb-2">Final Weight Calculation</h3>
                    <pre class="bg-gray-100 dark:bg-gray-700 p-3 rounded mt-2 text-sm font-mono">
EntropyScore = Entropy/log(5)
Weight = EntropyScore * RatingWeight

If total_ratings < minimum_threshold:
Weight *= (total_ratings/minimum_threshold)</pre>
                </li>
                <li>
                    <h3 class="text-base font-semibold mb-2">Suspicious Rater Adjustment</h3>
                    <p class="text-gray-600 dark:text-gray-300">If a rater is marked as suspicious, their weight is
                        reduced to 10% of the calculated value</p>
                </li>
            </ol>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3">Step 2: Calculating Game Scores</h2>
            <p class="text-gray-600 dark:text-gray-300 mb-4">The final game score uses several calculations:</p>
            <ol class="space-y-6">
                <li>
                    <h3 class="text-base font-semibold mb-2">Raw Weighted Rating</h3>
                    <p class="text-gray-600 dark:text-gray-300">Each rating is multiplied by the rater's weight</p>
                </li>
                <li>
                    <h3 class="text-base font-semibold mb-2">Minimum Votes Threshold</h3>
                    <p class="text-gray-600 dark:text-gray-300">Calculated using both mean and median votes:</p>
                    <pre class="bg-gray-100 dark:bg-gray-700 p-3 rounded mt-2 text-sm font-mono">
MeanBasedMin = 25% of mean votes per game
MedianBasedMin = 50% of median votes per game
MinVotes = min(MeanBasedMin, MedianBasedMin)
MinVotes = max(5, MinVotes)</pre>
                </li>
                <li>
                    <h3 class="text-base font-semibold mb-2">Bayesian Average</h3>
                    <p class="text-gray-600 dark:text-gray-300">Combines raw weighted rating with global mean:</p>
                    <pre class="bg-gray-100 dark:bg-gray-700 p-3 rounded mt-2 text-sm font-mono">
n = number of ratings
m = minimum votes threshold
R = weighted average rating
C = global weighted mean

Score = (n/(n+m)) * R + (m/(n+m)) * C</pre>
                </li>
                <li>
                    <h3 class="text-base font-semibold mb-2">Confidence Modifier</h3>
                    <p class="text-gray-600 dark:text-gray-300">Additional adjustment based on rating count:</p>
                    <pre class="bg-gray-100 dark:bg-gray-700 p-3 rounded mt-2 text-sm font-mono">
Confidence = 1 - e^(-0.1 * rating_count)
FinalScore = Score * Confidence</pre>
                </li>
            </ol>
        </section>

        <section>
            <h2 class="text-xl font-bold mb-3">Additional Notes</h2>
            <ul class="space-y-2 text-gray-600 dark:text-gray-300">
                <li>Scores are recalculated when new ratings are added or rater weights change.</li>
                <li>The confidence modifier ensures that games with very few ratings are ranked appropriately.</li>
                <li>Both the regular average score and weighted score are visible for comparison.</li>
            </ul>
        </section>
    </div>

    <x-dialog-footer/>
</dialog>
