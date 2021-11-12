<?php

/**
 * Chunked decoder. Only supports responses for now.
 */
class puzzle_subscriber_Chunked  implements puzzle_event_SubscriberInterface
{
    public function getEvents()
    {
        return array(
            'complete' => array('onComplete', puzzle_event_RequestEvents::VERIFY_RESPONSE - 20),
        );
    }

    public function onComplete(puzzle_event_CompleteEvent $event)
    {
        $transferInfo = $event->getTransferInfo();
        if (array_key_exists('http_code', $transferInfo)) {

            //curl
            return;
        }

        $response = $event->getResponse();

        if (!$response->hasHeader('Transfer-Encoding')) {

            return;
        }

        $encoding = $response->getHeader('Transfer-Encoding');

        if (strcasecmp($encoding, 'chunked') !== 0) {

            return;
        }

        $body        = $response->getBody()->__toString();
        $decodedBody = $this->_decode($body);

        $response->setBody(puzzle_stream_Stream::factory($decodedBody));
    }

    private function _decode($body)
    {
        /* http://tools.ietf.org/html/rfc2616#section-19.4.6 */

        /* first grab the initial chunk length */
        $chunkLengthPregMatchResult = preg_match('/^\s*([0-9a-fA-F]+)(?:(?!\r\n).)*\r\n/sm', $body, $chunkLengthMatches);

        if ($chunkLengthPregMatchResult === false || count($chunkLengthMatches) !== 2) {

            throw new RuntimeException('Data does not appear to be chunked (missing initial chunk length)');
        }

        /* set initial values */
        $currentOffsetIntoBody = strlen($chunkLengthMatches[0]);
        $currentChunkLength    = hexdec($chunkLengthMatches[1]);
        $decoded               = '';
        $bodyLength            = strlen($body);

        while ($currentChunkLength > 0) {

            /* read in the first chunk data */
            $decoded .= substr($body, $currentOffsetIntoBody, $currentChunkLength);

            /* increment the offset to what we just read */
            $currentOffsetIntoBody += $currentChunkLength;

            /* whoa nelly, we've hit the end of the road. */
            if ($currentOffsetIntoBody >= $bodyLength) {

                return $decoded;
            }

            /* grab the next chunk length */
            $chunkLengthPregMatchResult = preg_match('/\r\n\s*([0-9a-fA-F]+)(?:(?!\r\n).)*\r\n/sm', $body, $chunkLengthMatches, null, $currentOffsetIntoBody);

            if ($chunkLengthPregMatchResult === false || count($chunkLengthMatches) !== 2) {

                return $decoded;
            }

            /* increment the offset to start of next data */
            $currentOffsetIntoBody += strlen($chunkLengthMatches[0]);

            /* set up how much data we want to read */
            $currentChunkLength = hexdec($chunkLengthMatches[1]);
        }

        return $decoded;
    }
}
