/**
 * Speaks one voice note with Kokoro-82M (kokoro-js), entirely on this PC.
 *
 *   node scripts/kokoro.mjs <kokoro dir> <voice> <speed> <out.wav> [threads] < text
 *
 * <kokoro dir> holds node_modules/kokoro-js and the model under models/ (life-os's
 * helper/tools/kokoro, set up by its scripts/get-kokoro.mjs). Nothing is downloaded.
 */
import { join } from 'node:path'
import { pathToFileURL } from 'node:url'

const [dir, voice, speed = '1', out, threads = '6'] = process.argv.slice(2)
if (!dir || !voice || !out) {
  console.error('usage: node scripts/kokoro.mjs <kokoro dir> <voice> <speed> <out.wav> [threads] < text')
  process.exit(2)
}

const MODEL = 'onnx-community/Kokoro-82M-v1.0-ONNX'

let text = ''
for await (const chunk of process.stdin) text += chunk
text = text.trim()
if (!text) {
  console.error('no text on stdin')
  process.exit(2)
}

const { KokoroTTS } = await import(pathToFileURL(join(dir, 'node_modules/kokoro-js/dist/kokoro.js')).href)
const tf = await import(pathToFileURL(join(dir, 'node_modules/@huggingface/transformers/dist/transformers.node.mjs')).href)
tf.env.cacheDir = join(dir, 'models')
tf.env.allowRemoteModels = false

const model = await tf.StyleTextToSpeech2Model.from_pretrained(MODEL, {
  dtype: 'fp16',
  device: 'cpu',
  session_options: { intraOpNumThreads: Number(threads), interOpNumThreads: 1 },
})
const tts = new KokoroTTS(model, await tf.AutoTokenizer.from_pretrained(MODEL))

// Kokoro reads at most ~510 phonemes at once, so speak sentence by sentence and join them up
const pieces = []
let rate = 24000
const sentences = text.match(/[^.!?…]+[.!?…]+["')\]]*|[^.!?…]+$/g)?.map((s) => s.trim()).filter(Boolean) ?? [text]
for (const sentence of sentences) {
  const audio = await tts.generate(sentence, { voice, speed: Number(speed) || 1 })
  pieces.push(audio.audio)
  rate = audio.sampling_rate
  pieces.push(new Float32Array(Math.round(rate * 0.12))) // a breath between sentences
}

const total = pieces.reduce((n, p) => n + p.length, 0)
const joined = new Float32Array(total)
let at = 0
for (const p of pieces) {
  joined.set(p, at)
  at += p.length
}

const { RawAudio } = tf
await new RawAudio(joined, rate).save(out)
console.log(JSON.stringify({ ok: true, seconds: total / rate }))
